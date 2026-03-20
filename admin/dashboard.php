<?php
session_start();
require_once('../config.php');
require_once('../vendor/autoload.php');
require_once('../vendor/autoload.php');
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Webinar version selector logic
if (isset($_GET['webinar_version'])) {
    $_SESSION['webinar_version'] = ($_GET['webinar_version'] === 'v2') ? 'v2' : 'v1';
}
$webinar_version = $_SESSION['webinar_version'] ?? 'v1';
$webinar_table = ($webinar_version === 'v2') ? 'webinarv2' : 'webinar';

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// ===== Banner Settings (create table, load, save) =====
try {
    $pdo = getDBConnection();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            banner_enabled TINYINT(1) NOT NULL DEFAULT 1,
            banner_text VARCHAR(255) NOT NULL DEFAULT 'CODIGO DE DESCUENTO : CAPITAN01',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_banner'])) {
        $enabled = isset($_POST['banner_enabled']) ? 1 : 0;
        $text = trim((string)($_POST['banner_text'] ?? ''));
        if ($text === '') { $text = 'CODIGO DE DESCUENTO : CAPITAN01'; }
        $exists = $pdo->query("SELECT COUNT(*) AS c FROM site_settings")->fetch();
        if (($exists['c'] ?? 0) > 0) {
            $stmt = $pdo->prepare("UPDATE site_settings SET banner_enabled = ?, banner_text = ? WHERE id = 1");
            $stmt->execute([$enabled, $text]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO site_settings (id, banner_enabled, banner_text) VALUES (1, ?, ?)");
            $stmt->execute([$enabled, $text]);
        }
        $banner_saved = true;
    }
    $settings = $pdo->query("SELECT * FROM site_settings WHERE id = 1")->fetch();
    $banner_enabled = $settings ? (int)$settings['banner_enabled'] === 1 : true;
    $banner_text = $settings ? (string)$settings['banner_text'] : 'CODIGO DE DESCUENTO : CAPITAN01';
} catch (Exception $e) {
    $banner_enabled = true;
    $banner_text = 'CODIGO DE DESCUENTO : CAPITAN01';
}
function stripeOrderIsInstallments($paymentIntentId) {
    static $cache = [];
    $id = (string)$paymentIntentId;
    if ($id === '') return false;
    if (isset($cache[$id])) return $cache[$id];
    try {
        $pi = \Stripe\PaymentIntent::retrieve($id);
        $is = false;
        if ($pi && $pi->charges && $pi->charges->data && count($pi->charges->data) > 0) {
            $charge = $pi->charges->data[0];
            $details = $charge->payment_method_details;
            if ($details && $details->card && $details->card->installments && $details->card->installments->plan) {
                $is = true;
            }
        }
        $cache[$id] = $is;
        return $is;
    } catch (\Exception $e) {
        $cache[$id] = false;
        return false;
    }
}
function webinarNormalizePhoneDigits($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    $digits = ltrim($digits, '0');
    if (substr($digits, 0, 2) === '52' && strlen($digits) > 10) {
        $digits = substr($digits, 2);
        if (substr($digits, 0, 1) === '1' && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }
    }
    return $digits;
}

function webinarExtractLada($phone) {
    $digits = webinarNormalizePhoneDigits($phone);
    if (substr($digits, 0, 2) === '52' && strlen($digits) === 10) {
        $two = substr($digits, 2, 2);
        $twoDigit = ['33' => true, '55' => true, '56' => true, '81' => true];
        if (isset($twoDigit[$two])) return $two;
        return substr($digits, 2, 3);
    }
    if (strlen($digits) < 10) return '';
    $two = substr($digits, 0, 2);
    $twoDigit = ['33' => true, '55' => true, '56' => true, '81' => true];
    if (isset($twoDigit[$two])) return $two;
    return substr($digits, 0, 3);
}

function webinarMexicoStateFromLada($lada) {
    $lada = (string)$lada;
    $twoDigitStates = [
        '33' => 'Jalisco',
        '55' => 'CDMX / Estado de México',
        '56' => 'CDMX / Estado de México',
        '81' => 'Nuevo León',
    ];
    if (isset($twoDigitStates[$lada])) return $twoDigitStates[$lada];

    $threeDigitStates = [
        // Aguascalientes
        '449' => 'Aguascalientes', '458' => 'Aguascalientes', '465' => 'Aguascalientes', '495' => 'Aguascalientes', '496' => 'Aguascalientes',
        // Baja California
        '616' => 'Baja California', '646' => 'Baja California', '653' => 'Baja California', '658' => 'Baja California', '661' => 'Baja California', '663' => 'Baja California', '664' => 'Baja California', '665' => 'Baja California', '686' => 'Baja California',
        // Baja California Sur
        '612' => 'Baja California Sur', '613' => 'Baja California Sur', '615' => 'Baja California Sur', '624' => 'Baja California Sur',
        // Campeche
        '913' => 'Campeche', '938' => 'Campeche', '981' => 'Campeche', '982' => 'Campeche', '983' => 'Campeche', '996' => 'Campeche',
        // Chiapas
        '916' => 'Chiapas', '917' => 'Chiapas', '918' => 'Chiapas', '919' => 'Chiapas', '932' => 'Chiapas', '934' => 'Chiapas', '961' => 'Chiapas', '962' => 'Chiapas', '963' => 'Chiapas', '964' => 'Chiapas', '965' => 'Chiapas', '966' => 'Chiapas', '967' => 'Chiapas', '968' => 'Chiapas', '992' => 'Chiapas', '994' => 'Chiapas',
        // Chihuahua
        '614' => 'Chihuahua', '621' => 'Chihuahua', '625' => 'Chihuahua', '626' => 'Chihuahua', '627' => 'Chihuahua', '628' => 'Chihuahua', '629' => 'Chihuahua', '635' => 'Chihuahua', '636' => 'Chihuahua', '639' => 'Chihuahua', '648' => 'Chihuahua', '649' => 'Chihuahua', '652' => 'Chihuahua', '656' => 'Chihuahua', '657' => 'Chihuahua', '659' => 'Chihuahua',
        // Coahuila
        '671' => 'Coahuila', '842' => 'Coahuila', '844' => 'Coahuila', '861' => 'Coahuila', '862' => 'Coahuila', '864' => 'Coahuila', '866' => 'Coahuila', '867' => 'Coahuila', '869' => 'Coahuila', '871' => 'Coahuila', '872' => 'Coahuila', '873' => 'Coahuila', '877' => 'Coahuila', '878' => 'Coahuila',
        // Colima
        '312' => 'Colima', '313' => 'Colima', '314' => 'Colima',
        // Durango
        '618' => 'Durango', '674' => 'Durango', '675' => 'Durango', '676' => 'Durango', '677' => 'Durango',
        // Guanajuato
        '352' => 'Guanajuato', '411' => 'Guanajuato', '412' => 'Guanajuato', '413' => 'Guanajuato', '415' => 'Guanajuato', '417' => 'Guanajuato', '418' => 'Guanajuato', '419' => 'Guanajuato', '421' => 'Guanajuato', '428' => 'Guanajuato', '429' => 'Guanajuato', '432' => 'Guanajuato', '438' => 'Guanajuato', '442' => 'Guanajuato', '445' => 'Guanajuato', '456' => 'Guanajuato', '461' => 'Guanajuato', '462' => 'Guanajuato', '464' => 'Guanajuato', '466' => 'Guanajuato', '468' => 'Guanajuato', '469' => 'Guanajuato', '472' => 'Guanajuato', '473' => 'Guanajuato', '476' => 'Guanajuato', '477' => 'Guanajuato', '479' => 'Guanajuato',
        // Guerrero
        '721' => 'Guerrero', '727' => 'Guerrero', '732' => 'Guerrero', '733' => 'Guerrero', '736' => 'Guerrero', '741' => 'Guerrero', '742' => 'Guerrero', '744' => 'Guerrero', '745' => 'Guerrero', '747' => 'Guerrero', '753' => 'Guerrero', '754' => 'Guerrero', '755' => 'Guerrero', '756' => 'Guerrero', '757' => 'Guerrero', '758' => 'Guerrero', '762' => 'Guerrero', '767' => 'Guerrero', '781' => 'Guerrero',
        // Hidalgo
        '441' => 'Hidalgo', '483' => 'Hidalgo', '591' => 'Hidalgo', '738' => 'Hidalgo', '743' => 'Hidalgo', '746' => 'Hidalgo', '748' => 'Hidalgo', '759' => 'Hidalgo', '761' => 'Hidalgo', '763' => 'Hidalgo', '771' => 'Hidalgo', '772' => 'Hidalgo', '773' => 'Hidalgo', '774' => 'Hidalgo', '775' => 'Hidalgo', '776' => 'Hidalgo', '778' => 'Hidalgo', '779' => 'Hidalgo', '789' => 'Hidalgo', '791' => 'Hidalgo',
        // Jalisco
        '315' => 'Jalisco', '316' => 'Jalisco', '317' => 'Jalisco', '321' => 'Jalisco', '322' => 'Jalisco', '326' => 'Jalisco', '341' => 'Jalisco', '342' => 'Jalisco', '343' => 'Jalisco', '344' => 'Jalisco', '345' => 'Jalisco', '346' => 'Jalisco', '347' => 'Jalisco', '348' => 'Jalisco', '349' => 'Jalisco', '354' => 'Jalisco', '357' => 'Jalisco', '358' => 'Jalisco', '371' => 'Jalisco', '372' => 'Jalisco', '373' => 'Jalisco', '374' => 'Jalisco', '375' => 'Jalisco', '376' => 'Jalisco', '377' => 'Jalisco', '378' => 'Jalisco', '382' => 'Jalisco', '384' => 'Jalisco', '385' => 'Jalisco', '386' => 'Jalisco', '387' => 'Jalisco', '388' => 'Jalisco', '391' => 'Jalisco', '392' => 'Jalisco', '393' => 'Jalisco', '395' => 'Jalisco', '424' => 'Jalisco', '431' => 'Jalisco', '437' => 'Jalisco', '457' => 'Jalisco', '474' => 'Jalisco', '475' => 'Jalisco', '499' => 'Jalisco',
        // Estado de México
        '427' => 'Estado de México', '588' => 'Estado de México', '592' => 'Estado de México', '593' => 'Estado de México', '594' => 'Estado de México', '595' => 'Estado de México', '596' => 'Estado de México', '597' => 'Estado de México', '599' => 'Estado de México', '711' => 'Estado de México', '712' => 'Estado de México', '713' => 'Estado de México', '714' => 'Estado de México', '716' => 'Estado de México', '717' => 'Estado de México', '718' => 'Estado de México', '719' => 'Estado de México', '722' => 'Estado de México', '723' => 'Estado de México', '724' => 'Estado de México', '725' => 'Estado de México', '726' => 'Estado de México', '728' => 'Estado de México', '729' => 'Estado de México', '751' => 'Estado de México',
        // Michoacán
        '328' => 'Michoacán', '351' => 'Michoacán', '353' => 'Michoacán', '355' => 'Michoacán', '356' => 'Michoacán', '359' => 'Michoacán', '381' => 'Michoacán', '383' => 'Michoacán', '394' => 'Michoacán', '422' => 'Michoacán', '423' => 'Michoacán', '425' => 'Michoacán', '426' => 'Michoacán', '434' => 'Michoacán', '435' => 'Michoacán', '436' => 'Michoacán', '443' => 'Michoacán', '447' => 'Michoacán', '451' => 'Michoacán', '452' => 'Michoacán', '453' => 'Michoacán', '454' => 'Michoacán', '455' => 'Michoacán', '459' => 'Michoacán', '471' => 'Michoacán', '715' => 'Michoacán', '786' => 'Michoacán',
        // Morelos
        '731' => 'Morelos', '734' => 'Morelos', '735' => 'Morelos', '737' => 'Morelos', '739' => 'Morelos', '769' => 'Morelos', '777' => 'Morelos',
        // Nayarit
        '311' => 'Nayarit', '319' => 'Nayarit', '323' => 'Nayarit', '324' => 'Nayarit', '325' => 'Nayarit', '327' => 'Nayarit', '329' => 'Nayarit', '389' => 'Nayarit',
        // Nuevo León
        '488' => 'Nuevo León', '821' => 'Nuevo León', '823' => 'Nuevo León', '824' => 'Nuevo León', '825' => 'Nuevo León', '826' => 'Nuevo León', '828' => 'Nuevo León', '829' => 'Nuevo León', '892' => 'Nuevo León',
        // Oaxaca
        '236' => 'Oaxaca', '274' => 'Oaxaca', '281' => 'Oaxaca', '283' => 'Oaxaca', '287' => 'Oaxaca', '924' => 'Oaxaca', '951' => 'Oaxaca', '953' => 'Oaxaca', '954' => 'Oaxaca', '958' => 'Oaxaca', '971' => 'Oaxaca', '972' => 'Oaxaca', '995' => 'Oaxaca',
        // Puebla
        '220' => 'Puebla', '221' => 'Puebla', '222' => 'Puebla', '223' => 'Puebla', '224' => 'Puebla', '226' => 'Puebla', '227' => 'Puebla', '231' => 'Puebla', '232' => 'Puebla', '233' => 'Puebla', '237' => 'Puebla', '238' => 'Puebla', '243' => 'Puebla', '244' => 'Puebla', '245' => 'Puebla', '248' => 'Puebla', '249' => 'Puebla', '275' => 'Puebla', '276' => 'Puebla', '278' => 'Puebla', '282' => 'Puebla', '764' => 'Puebla', '797' => 'Puebla',
        // Querétaro
        '414' => 'Querétaro', '441' => 'Querétaro', '442' => 'Querétaro', '446' => 'Querétaro', '448' => 'Querétaro', '487' => 'Querétaro',
        // Quintana Roo
        '984' => 'Quintana Roo', '987' => 'Quintana Roo', '997' => 'Quintana Roo', '998' => 'Quintana Roo',
        // San Luis Potosí
        '440' => 'San Luis Potosí', '444' => 'San Luis Potosí', '481' => 'San Luis Potosí', '482' => 'San Luis Potosí', '485' => 'San Luis Potosí', '486' => 'San Luis Potosí', '489' => 'San Luis Potosí', '845' => 'San Luis Potosí',
        // Sinaloa
        '667' => 'Sinaloa', '668' => 'Sinaloa', '669' => 'Sinaloa', '672' => 'Sinaloa', '673' => 'Sinaloa', '687' => 'Sinaloa', '694' => 'Sinaloa', '695' => 'Sinaloa', '696' => 'Sinaloa', '697' => 'Sinaloa', '698' => 'Sinaloa',
        // Sonora
        '622' => 'Sonora', '623' => 'Sonora', '631' => 'Sonora', '632' => 'Sonora', '633' => 'Sonora', '634' => 'Sonora', '637' => 'Sonora', '638' => 'Sonora', '641' => 'Sonora', '642' => 'Sonora', '643' => 'Sonora', '644' => 'Sonora', '645' => 'Sonora', '647' => 'Sonora', '651' => 'Sonora', '662' => 'Sonora',
        // Tabasco
        '914' => 'Tabasco', '923' => 'Tabasco', '933' => 'Tabasco', '936' => 'Tabasco', '937' => 'Tabasco', '993' => 'Tabasco',
        // Tamaulipas
        '482' => 'Tamaulipas', '831' => 'Tamaulipas', '832' => 'Tamaulipas', '833' => 'Tamaulipas', '834' => 'Tamaulipas', '835' => 'Tamaulipas', '836' => 'Tamaulipas', '841' => 'Tamaulipas', '891' => 'Tamaulipas', '894' => 'Tamaulipas', '897' => 'Tamaulipas', '899' => 'Tamaulipas',
        // Tlaxcala
        '241' => 'Tlaxcala', '246' => 'Tlaxcala', '247' => 'Tlaxcala', '749' => 'Tlaxcala',
        // Veracruz
        '225' => 'Veracruz', '228' => 'Veracruz', '229' => 'Veracruz', '235' => 'Veracruz', '271' => 'Veracruz', '272' => 'Veracruz', '279' => 'Veracruz', '284' => 'Veracruz', '285' => 'Veracruz', '288' => 'Veracruz', '294' => 'Veracruz', '296' => 'Veracruz', '297' => 'Veracruz', '765' => 'Veracruz', '766' => 'Veracruz', '768' => 'Veracruz', '782' => 'Veracruz', '783' => 'Veracruz', '784' => 'Veracruz', '785' => 'Veracruz', '846' => 'Veracruz', '921' => 'Veracruz', '922' => 'Veracruz',
        // Yucatán
        '969' => 'Yucatán', '985' => 'Yucatán', '986' => 'Yucatán', '988' => 'Yucatán', '990' => 'Yucatán', '991' => 'Yucatán', '999' => 'Yucatán',
        // Zacatecas
        '433' => 'Zacatecas', '463' => 'Zacatecas', '467' => 'Zacatecas', '478' => 'Zacatecas', '492' => 'Zacatecas', '493' => 'Zacatecas', '494' => 'Zacatecas', '498' => 'Zacatecas',
        // USA
        '575' => 'Nuevo México, EE. UU.',
        '707' => 'California, EE. UU.',
    ];

    return $threeDigitStates[$lada] ?? '';
}

$installmentsCache = [];
function stripeUsesInstallments($paymentIntentId) {
    global $installmentsCache;
    $pid = (string)$paymentIntentId;
    if ($pid === '') return false;
    if (array_key_exists($pid, $installmentsCache)) return $installmentsCache[$pid];
    try {
        $pi = \Stripe\PaymentIntent::retrieve($pid);
        $hasCharges = isset($pi->charges) && isset($pi->charges->data) && is_array($pi->charges->data) && count($pi->charges->data) > 0;
        $charge = $hasCharges ? $pi->charges->data[0] : null;
        $details = $charge && isset($charge->payment_method_details) ? $charge->payment_method_details : null;
        $card = $details && isset($details->card) ? $details->card : null;
        $installments = $card && isset($card->installments) ? $card->installments : null;
        $isInstallments = ($installments && isset($installments->plan) && $installments->plan) ? true : false;
        $installmentsCache[$pid] = $isInstallments;
        return $isInstallments;
    } catch (\Exception $e) {
        $installmentsCache[$pid] = false;
        return false;
    }
}

function computeNetAmountForOrder($order) {
    $totalCents = isset($order['total_amount_cents']) ? (int)$order['total_amount_cents'] : (int)round(((float)$order['total_amount']) * 100);
    $pid = isset($order['stripe_payment_intent_id']) ? (string)$order['stripe_payment_intent_id'] : '';
    $isInstallments = stripeUsesInstallments($pid);
    $percent = $isInstallments ? 0.086 : 0.036;
    $feeCents = (int)round($totalCents * $percent) + 300;
    $netCents = $totalCents - $feeCents;
    if ($netCents < 0) $netCents = 0;
    return $netCents / 100.0;
}

if (isset($_GET['export']) && $_GET['export'] === 'webinar') {
    try {
        $pdo = getDBConnection();
        $rows = $pdo->query("
            SELECT nombre_completo, correo_electronico, numero_telefono, utm_source, created_at
            FROM {$webinar_table}
            ORDER BY created_at DESC
        ")->fetchAll();
    } catch (PDOException $e) {
        http_response_code(500);
        exit;
    }

    $csvSafe = static function ($value) {
        $value = (string)($value ?? '');
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        if ($value !== '' && preg_match('/^[=\+\-@]/', $value)) {
            return "'" . $value;
        }
        return $value;
    };

    $format = strtolower((string)($_GET['format'] ?? 'xlsx'));
    $canXlsx = false;
    if ($format === 'xlsx') {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoload) && extension_loaded('zip')) {
            require_once $autoload;
            if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
                $canXlsx = true;
            }
        }
    }

    if ($format === 'xlsx' && $canXlsx) {
        $filename = 'webinar_' . date('Y-m-d_H-i-s') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Webinar');

        $sheet->setCellValue('A1', 'Nombre completo');
        $sheet->setCellValue('B1', 'Correo electrónico');
        $sheet->setCellValue('C1', 'Número de teléfono');
        $sheet->setCellValue('D1', 'Red Social');
        $sheet->setCellValue('E1', 'Estado');
        $sheet->setCellValue('F1', 'Fecha registro');

        $rowIndex = 2;
        foreach ($rows as $row) {
            $source = strtolower(trim((string)($row['utm_source'] ?? '')));
            $sourceLabel = $source === '' ? '' : (in_array($source, ['ig', 'instagram', 'insta'], true) ? 'Instagram' : (string)($row['utm_source'] ?? ''));
            $lada = webinarExtractLada($row['numero_telefono'] ?? '');
            $state = webinarMexicoStateFromLada($lada);
            $stateLabel = $state === '' ? 'Sin Informacion' : $state;

            $sheet->setCellValueExplicit("A{$rowIndex}", (string)($row['nombre_completo'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("B{$rowIndex}", (string)($row['correo_electronico'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$rowIndex}", (string)($row['numero_telefono'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("D{$rowIndex}", (string)$sourceLabel, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("E{$rowIndex}", (string)$stateLabel, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$rowIndex}", (string)($row['created_at'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $rowIndex++;
        }

        foreach (['A','B','C','D','E','F'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    $filename = 'webinar_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo "\xEF\xBB\xBF";
    echo "sep=;\n";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Nombre completo', 'Correo electrónico', 'Número de teléfono', 'Red Social', 'Estado', 'Fecha registro'], ';');
    foreach ($rows as $row) {
        $source = strtolower(trim((string)($row['utm_source'] ?? '')));
        $sourceLabel = $source === '' ? 'Link Directo' : (in_array($source, ['ig', 'instagram', 'insta'], true) ? 'Instagram' : (string)($row['utm_source'] ?? ''));
        $lada = webinarExtractLada($row['numero_telefono'] ?? '');
        $state = webinarMexicoStateFromLada($lada);
        $stateLabel = $state === '' ? 'Sin Informacion' : $state;
        fputcsv(
            $out,
            [
                $csvSafe($row['nombre_completo'] ?? ''),
                $csvSafe($row['correo_electronico'] ?? ''),
                $csvSafe($row['numero_telefono'] ?? ''),
                $csvSafe($sourceLabel),
                $csvSafe($stateLabel),
                $csvSafe($row['created_at'] ?? ''),
            ],
            ';'
        );
    }
    fclose($out);
    exit;
}

// Get data from database
try {
    $pdo = getDBConnection();
    
    // Date Filter Logic
    $filter_date = $_GET['filter_date'] ?? null;
    $start_date = null;
    $end_date = null;
    $date_params = [];

    if ($filter_date) {
        // Extract dates using regex to be robust against separators and whitespace
        preg_match_all('/(\d{4}-\d{2}-\d{2})/', $filter_date, $matches);
        $dates = $matches[0] ?? [];

        if (count($dates) > 0) {
            $start_date = $dates[0];
            $end_date = count($dates) > 1 ? $dates[1] : $start_date;
            $date_params = [$start_date, $end_date];
        } else {
            // No valid dates found, disable filter
            $filter_date = null;
            $start_date = null;
            $end_date = null;
            $date_params = [];
        }
    }

    // Helper for simple WHERE clauses
    $simple_date_where = $filter_date ? "WHERE DATE(DATE_SUB(created_at, INTERVAL 6 HOUR)) BETWEEN ? AND ?" : "";

    // Get newsletter subscribers
    $sql = "SELECT * FROM subscribers $simple_date_where ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    if ($filter_date) $stmt->execute($date_params); else $stmt->execute();
    $subscribers = $stmt->fetchAll();
    
    // Get form submissions
    $sql = "SELECT * FROM form_submissions $simple_date_where ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    if ($filter_date) $stmt->execute($date_params); else $stmt->execute();
    $form_submissions = $stmt->fetchAll();
    
    // Get traffic tracking data with UTM parameters
    $sql = "
        SELECT tt.*, o.order_number, o.total_amount, o.status as order_status,
               c.name as customer_name, c.email as customer_email,
               p.name as product_name
        FROM traffic_tracking tt
        LEFT JOIN orders o ON tt.order_id = o.id
        LEFT JOIN customers c ON tt.customer_id = c.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
    ";
    if ($filter_date) $sql .= " WHERE DATE(DATE_SUB(tt.created_at, INTERVAL 6 HOUR)) BETWEEN ? AND ? ";
    $sql .= " ORDER BY tt.created_at DESC";
    $stmt = $pdo->prepare($sql);
    if ($filter_date) $stmt->execute($date_params); else $stmt->execute();
    $traffic_data = $stmt->fetchAll();
    
    // Get UTM statistics
    $sql = "
        SELECT 
            utm_source,
            utm_medium,
            utm_campaign,
            COUNT(DISTINCT user_fingerprint) as unique_visits,
            COUNT(CASE WHEN order_id IS NOT NULL THEN 1 END) as conversions,
            ROUND(COUNT(CASE WHEN order_id IS NOT NULL THEN 1 END) * 100.0 / COUNT(DISTINCT user_fingerprint), 2) as conversion_rate
        FROM traffic_tracking
        WHERE (utm_source IS NOT NULL OR utm_medium IS NOT NULL OR utm_campaign IS NOT NULL)
    ";
    if ($filter_date) $sql .= " AND DATE(DATE_SUB(created_at, INTERVAL 6 HOUR)) BETWEEN ? AND ? ";
    $sql .= " GROUP BY utm_source, utm_medium, utm_campaign ORDER BY unique_visits DESC";
    $stmt = $pdo->prepare($sql);
    if ($filter_date) $stmt->execute($date_params); else $stmt->execute();
    $utm_stats = $stmt->fetchAll();
    
    // Get products sold by traffic source
    $sql = "
        SELECT 
            COALESCE(tt.utm_source, 'Directo') as traffic_source,
            p.name as product_name,
            COUNT(oi.id) as times_sold,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.total_price) as total_revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        LEFT JOIN traffic_tracking tt ON o.id = tt.order_id
        WHERE o.status = 'completed'
    ";
    if ($filter_date) $sql .= " AND DATE(DATE_SUB(o.created_at, INTERVAL 6 HOUR)) BETWEEN ? AND ? ";
    $sql .= " GROUP BY COALESCE(tt.utm_source, 'Directo'), p.name ORDER BY total_revenue DESC";
    $stmt = $pdo->prepare($sql);
    if ($filter_date) $stmt->execute($date_params); else $stmt->execute();
    $products_by_source = $stmt->fetchAll();
    
    // Get customers
    $sql = "SELECT * FROM customers $simple_date_where ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    if ($filter_date) $stmt->execute($date_params); else $stmt->execute();
    $customers = $stmt->fetchAll();
    
    // Get orders with customer details
    $sql = "
        SELECT o.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone
        FROM orders o 
        JOIN customers c ON o.customer_id = c.id 
    ";
    if ($filter_date) $sql .= " WHERE DATE(DATE_SUB(o.created_at, INTERVAL 6 HOUR)) BETWEEN ? AND ? ";
    $sql .= " ORDER BY o.created_at DESC";
    $stmt = $pdo->prepare($sql);
    if ($filter_date) $stmt->execute($date_params); else $stmt->execute();
    $orders = $stmt->fetchAll();
    
    // Get payments
    $sql = "
        SELECT p.*, o.order_number, c.name as customer_name, c.email as customer_email
        FROM payments p 
        JOIN orders o ON p.order_id = o.id 
        JOIN customers c ON o.customer_id = c.id 
    ";
    if ($filter_date) $sql .= " WHERE DATE(DATE_SUB(p.created_at, INTERVAL 6 HOUR)) BETWEEN ? AND ? ";
    $sql .= " ORDER BY p.created_at DESC";
    $stmt = $pdo->prepare($sql);
    if ($filter_date) $stmt->execute($date_params); else $stmt->execute();
    $payments = $stmt->fetchAll();
    
    // Get order items with product details
    $sql = "
        SELECT oi.*, o.order_number, p.name as product_name, c.name as customer_name
        FROM order_items oi 
        JOIN orders o ON oi.order_id = o.id 
        JOIN products p ON oi.product_id = p.id 
        JOIN customers c ON o.customer_id = c.id 
    ";
    if ($filter_date) $sql .= " WHERE DATE(DATE_SUB(oi.created_at, INTERVAL 6 HOUR)) BETWEEN ? AND ? ";
    $sql .= " ORDER BY oi.created_at DESC";
    $stmt = $pdo->prepare($sql);
    if ($filter_date) $stmt->execute($date_params); else $stmt->execute();
    $order_items = $stmt->fetchAll();
    
    // Webinar registrations
    $sql = "
        SELECT
            nombre_completo,
            correo_electronico,
            numero_telefono,
            utm_source,
            created_at
        FROM {$webinar_table}
    ";
    if ($filter_date) $sql .= " WHERE DATE(DATE_SUB(created_at, INTERVAL 6 HOUR)) BETWEEN ? AND ? ";
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    if ($filter_date) $stmt->execute($date_params); else $stmt->execute();
    $webinars = $stmt->fetchAll();

    // Mundial - Crear tabla si no existe y obtener registros
    $pdo->exec("CREATE TABLE IF NOT EXISTS registros (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL,
        correo VARCHAR(255) NOT NULL,
        telefono VARCHAR(10) NOT NULL,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_correo (correo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $sql = "SELECT * FROM registros ORDER BY fecha_registro DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $registros_mundial = $stmt->fetchAll();

    // Prepare data for Mundial chart (Registros por Estado)
    $mundialStateCounts = [];
    foreach ($registros_mundial as $r) {
        $lada = webinarExtractLada($r['telefono'] ?? '');
        $state = webinarMexicoStateFromLada($lada);
        if ($state === '') $state = 'Sin Información';
        if (!isset($mundialStateCounts[$state])) $mundialStateCounts[$state] = 0;
        $mundialStateCounts[$state]++;
    }
    arsort($mundialStateCounts);

    // Prepare data for charts
    $stateCounts = [];
    $sourceCounts = [];

    foreach ($webinars as $w) {
        // State logic
        $lada = webinarExtractLada($w['numero_telefono'] ?? '');
        $state = webinarMexicoStateFromLada($lada);
        if ($state === '') $state = 'Sin Informacion';
        if (!isset($stateCounts[$state])) $stateCounts[$state] = 0;
        $stateCounts[$state]++;

        // Source logic
        $source = strtolower(trim((string)($w['utm_source'] ?? '')));
        $sourceLabel = $source === '' ? 'Link Directo' : (in_array($source, ['ig', 'instagram', 'insta'], true) ? 'Instagram' : (string)($w['utm_source'] ?? ''));
        if (!isset($sourceCounts[$sourceLabel])) $sourceCounts[$sourceLabel] = 0;
        $sourceCounts[$sourceLabel]++;
    }

    // Sort counts for better visualization
    arsort($stateCounts);
    arsort($sourceCounts);
    
    // Get statistics
    // Total Customers
    $sql = "SELECT COUNT(*) FROM customers " . $simple_date_where;
    $stmt = $pdo->prepare($sql);
    if ($filter_date) $stmt->execute($date_params); else $stmt->execute();
    $total_customers = $stmt->fetchColumn();

    // Total Orders
    $sql = "SELECT COUNT(*) FROM orders " . $simple_date_where;
    $stmt = $pdo->prepare($sql);
    if ($filter_date) $stmt->execute($date_params); else $stmt->execute();
    $total_orders = $stmt->fetchColumn();

    // Total Revenue
    $sql = "SELECT SUM(total_amount) FROM orders WHERE status = 'completed'";
    if ($filter_date) $sql .= " AND DATE(DATE_SUB(created_at, INTERVAL 6 HOUR)) BETWEEN ? AND ?";
    $stmt = $pdo->prepare($sql);
    if ($filter_date) $stmt->execute($date_params); else $stmt->execute();
    $total_revenue = $stmt->fetchColumn();

    // Net Revenue
    $net_revenue = 0.0;
    foreach ($orders as $o) {
        $net_revenue += computeNetAmountForOrder($o);
    }
    
    // Total Subscribers
    $sql = "SELECT COUNT(*) FROM subscribers " . $simple_date_where;
    $stmt = $pdo->prepare($sql);
    if ($filter_date) $stmt->execute($date_params); else $stmt->execute();
    $total_subscribers = $stmt->fetchColumn();
    
    // Total UTM Visits
    $sql = "SELECT COUNT(DISTINCT user_fingerprint) FROM traffic_tracking WHERE (utm_source IS NOT NULL OR utm_medium IS NOT NULL OR utm_campaign IS NOT NULL)";
    if ($filter_date) $sql .= " AND DATE(DATE_SUB(created_at, INTERVAL 6 HOUR)) BETWEEN ? AND ?";
    $stmt = $pdo->prepare($sql);
    if ($filter_date) $stmt->execute($date_params); else $stmt->execute();
    $total_utm_visits = $stmt->fetchColumn();
    
    // UTM Conversions
    $sql = "SELECT COUNT(DISTINCT tt.user_fingerprint) FROM traffic_tracking tt JOIN orders o ON tt.order_id = o.id WHERE (tt.utm_source IS NOT NULL OR tt.utm_medium IS NOT NULL OR tt.utm_campaign IS NOT NULL)";
    if ($filter_date) $sql .= " AND DATE(DATE_SUB(o.created_at, INTERVAL 6 HOUR)) BETWEEN ? AND ?";
    $stmt = $pdo->prepare($sql);
    if ($filter_date) $stmt->execute($date_params); else $stmt->execute();
    $utm_conversions = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../Img/Logo.png" type="image/x-icon">
    <title>Admin Dashboard - Capitán Financiero</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        .admin-header {
            background: linear-gradient(135deg, #222F58 0%, #667eea 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 3rem;
        }

        .admin-header h1 {
            font-size: 2.5rem;
            font-weight: 300;
            margin-bottom: 0.5rem;
        }

        .admin-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .logout-btn {
            position: absolute;
            top: 2rem;
            right: 2rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
        }

        .stats-grid {
            display: flex;
            flex-wrap: nowrap;
            gap: 0.75rem;
            margin-bottom: 2rem;
            justify-content: center;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .stat-card {
            width: 190px;
            background: white;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #222F58;
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }

        .stat-card .icon {
            font-size: 1.2rem;
            color: #223058;
            margin-bottom: 1rem;
        }

        .nav-tabs {
            border: none;
            margin-bottom: 2rem;
            flex-wrap: nowrap;
            gap: 0.25rem;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            justify-content: center;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 0.65rem 1rem;
            font-size: 0.95rem;
            border-radius: 6px;
            margin-right: 0.25rem;
            transition: all 0.3s ease;
        }
        .nav-tabs .nav-link i {
            font-size: 1rem;
            margin-right: 0.35rem;
        }
        @media (max-width: 768px) {
            .nav-tabs .nav-link {
                padding: 0.55rem 0.85rem;
                font-size: 0.9rem;
            }
            .nav-tabs .nav-link i {
                font-size: 0.95rem;
            }
        }

        .nav-tabs .nav-link:hover {
            background-color: #f8f9fa;
            color: #222F58;
        }

        .nav-tabs .nav-link.active {
            background-color: #222F58;
            color: white;
            border: none;
        }

        .tab-content {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            border: none;
            background-color: #f8f9fa;
            color: #222F58;
            font-weight: 600;
            padding: 0.75rem;
            font-size: 0.9rem;
        }

        .table td {
            border: none;
            padding: 0.75rem;
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .table tbody tr {
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 500;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 6px;
        }

        .btn-info {
            background-color: #223058 !important;
            border-color: #223058 !important;
            color: white !important;
        }
        
        .btn-info:hover {
            background-color: #223058 !important;
            border-color: #223058 !important;
            color: white !important;
        }
        
        .btn-info:focus {
            background-color: #223058 !important;
            border-color: #223058 !important;
            color: white !important;
            box-shadow: none !important;
            outline: none !important;
        }
        
        .btn-info:active {
            background-color: #223058 !important;
            border-color: #223058 !important;
            color: white !important;
            box-shadow: none !important;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            margin: 1rem 0;
        }

        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 0.375rem 0.75rem;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            outline: none;
        }

        .page-link {
            color: #667eea;
            border-color: #dee2e6;
        }

        .page-link:hover {
            color: #222F58;
            background-color: #f8f9fa;
            border-color: #dee2e6;
        }

        .page-item.active .page-link {
            background-color: #222F58;
            border-color: #222F58;
        }

        /* Estilos para el modal */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            max-width: 95%;
        }

        .modal-header {
            background: linear-gradient(135deg, #222F58 0%, #667eea 100%);
            color: white;
            border-radius: 12px 12px 0 0;
            border: none;
        }

        .modal-title {
            font-weight: 600;
        }

        .btn-close {
            filter: invert(1);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .table-sm th,
        .table-sm td {
            padding: 0.4rem;
            font-size: 0.85rem;
        }

        .table-sm th {
            background-color: #f8f9fa;
            color: #222F58;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .admin-header h1 {
                font-size: 2rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .nav-tabs .nav-link {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }

            .tab-content {
                padding: 1rem;
            }

            .logout-btn {
                position: relative;
                top: auto;
                right: auto;
                margin-bottom: 1rem;
            }
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 1rem;
            }
        }

        @media (max-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 1rem;
            }
        }

        /* Webinar Version Selector - Minimalista */
        .webinar-version-selector {
            position: absolute;
            top: 0;
            right: 0;
        }

        .webinar-version-btn {
            border-radius: 20px;
            padding: 0.375rem 1rem;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid #dee2e6;
            background-color: #fff;
            color: #6c757d;
            transition: all 0.2s ease;
        }

        .webinar-version-btn:hover {
            border-color: #adb5bd;
            background-color: #f8f9fa;
        }

        .webinar-version-btn:focus {
            box-shadow: none;
            border-color: #222F58;
        }

        .webinar-version-btn .fa-database {
            margin-right: 0.4rem;
        }

        .webinar-version-btn .version-text {
            color: #495057;
        }

        @media (max-width: 768px) {
            .webinar-version-selector {
                position: static;
                margin-top: 1rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
            <h1>Dashboard Administrativo</h1>
            <p>Gestión de clientes, órdenes y pagos</p>
        </div>
    </div>

    <div class="container">
        <!-- Date Filter -->
        <form method="GET" class="mb-4" id="filterForm">
            <div style="max-width: 1200px; margin: 0 auto;">
                <div class="row align-items-end justify-content-end" style="transform: translateX(15px);">
                    <div class="col-auto">
                        <label for="filter_date" class="form-label fw-bold">Filtrar por Fecha:</label>
                        <input type="text" class="form-control" id="filter_date" name="filter_date" value="<?php echo htmlspecialchars($_GET['filter_date'] ?? ''); ?>" placeholder="Seleccionar fecha(s)">
                    </div>
                    <div class="col-auto">
                        <?php if (isset($_GET['filter_date']) && $_GET['filter_date']): ?>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                flatpickr("#filter_date", {
                    mode: "range",
                    dateFormat: "Y-m-d",
                    locale: "es",
                    onClose: function(selectedDates, dateStr, instance) {
                        if (dateStr) {
                            document.getElementById('filterForm').submit();
                        }
                    }
                });
            });
        </script>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3><?php echo $total_customers ?? 0; ?></h3>
                <p>Clientes Registrados</p>
            </div>
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h3><?php echo $total_orders ?? 0; ?></h3>
                <p>Órdenes Totales</p>
            </div>
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <h3>$<?php echo number_format($total_revenue ?? 0, 2); ?></h3>
                <p>Ingresos Totales</p>
            </div>
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <h3>$<?php echo number_format($net_revenue ?? 0, 2); ?></h3>
                <p>Ingreso Neto (Stripe)</p>
            </div>
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <h3><?php echo $total_subscribers ?? 0; ?></h3>
                <p>Suscriptores Newsletter</p>
            </div>
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h3><?php echo $total_utm_visits ?? 0; ?></h3>
                <p>Visitas con UTM</p>
            </div>
        </div>



        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button">
                    <i class="fas fa-shopping-cart"></i> Órdenes
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="customers-tab" data-bs-toggle="tab" data-bs-target="#customers" type="button">
                    <i class="fas fa-users"></i> Clientes
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button">
                    <i class="fas fa-credit-card"></i> Pagos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="newsletter-tab" data-bs-toggle="tab" data-bs-target="#newsletter" type="button">
                    <i class="fas fa-envelope"></i> Suscriptores
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="forms-tab" data-bs-toggle="tab" data-bs-target="#forms" type="button">
                    <i class="fas fa-file-alt"></i> Formularios
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="traffic-tab" data-bs-toggle="tab" data-bs-target="#traffic" type="button">
                    <i class="fas fa-chart-bar"></i> Tráfico ManyChat
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="qr-tab" data-bs-toggle="tab" data-bs-target="#qr" type="button">
                    <i class="fas fa-qrcode"></i> Generar QR
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="webinar-tab" data-bs-toggle="tab" data-bs-target="#webinar" type="button">
                    <i class="fas fa-video"></i> Webinar
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button">
                    <i class="fas fa-bullhorn"></i> Banner
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="manual-email-tab" data-bs-toggle="tab" data-bs-target="#manual-email" type="button">
                    <i class="fas fa-paper-plane"></i> Envíos Manuales
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="mundial-tab" data-bs-toggle="tab" data-bs-target="#mundial" type="button">
                    <i class="fas fa-globe"></i> Mundial
                </button>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <!-- Settings Tab -->
            <div class="tab-pane fade" id="settings">
                <div class="card mb-4">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><i class="fas fa-bullhorn"></i> Banner Promocional</h5>
                        <?php if (!empty($banner_saved)): ?>
                            <span class="badge bg-success">Guardado</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="banner_enabled" name="banner_enabled" <?php echo $banner_enabled ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="banner_enabled">Mostrar banner en el sitio</label>
                            </div>
                            <div class="mb-3">
                                <label for="banner_text" class="form-label">Texto del banner</label>
                                <input type="text" class="form-control" id="banner_text" name="banner_text" value="<?php echo htmlspecialchars($banner_text); ?>" placeholder="Ingresa el texto del banner">
                                <div class="form-text">Este texto se repetirá para el efecto de carrusel.</div>
                            </div>
                            <button type="submit" class="btn btn-primary" name="save_banner"><i class="fas fa-save"></i> Guardar cambios</button>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Manual Email Tab -->
            <div class="tab-pane fade" id="manual-email">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-paper-plane"></i> Enviar Correo Manualmente</h5>
                    </div>
                    <div class="card-body">
                        <form id="manualEmailForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="customer_name" class="form-label">Nombre del Cliente</label>
                                    <input type="text" class="form-control" id="customer_name" name="customer_name" required placeholder="Ej. Juan Pérez">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="customer_email" class="form-label">Email del Cliente</label>
                                    <input type="email" class="form-control" id="customer_email" name="customer_email" required placeholder="Ej. juan@email.com">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="product_name" class="form-label">Producto Comprado</label>
                                    <input type="text" class="form-control" id="product_name" name="product_name" value="Los 6 Pasos para tu Independencia Financiera" placeholder="Ej. Curso Avanzado">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="price" class="form-label">Precio ($)</label>
                                    <input type="number" step="0.01" class="form-control" id="price" name="price" value="997.00">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="order_id" class="form-label">Número de Orden</label>
                                    <input type="text" class="form-control" id="order_id" name="order_id" required placeholder="Ej. ORD-12345">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Enviar Correo
                            </button>
                        </form>
                        <div id="emailResult" class="mt-3" style="display:none;"></div>
                    </div>
                </div>
                
                <script>
                document.getElementById('manualEmailForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    var formData = new FormData(this);
                    var resultDiv = document.getElementById('emailResult');
                    var submitBtn = this.querySelector('button[type="submit"]');
                    
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
                    resultDiv.style.display = 'none';
                    resultDiv.className = 'mt-3 alert';
                    
                    fetch('manual_email_send.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        resultDiv.style.display = 'block';
                        if (data.success) {
                            resultDiv.className = 'mt-3 alert alert-success';
                            resultDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                            this.reset();
                        } else {
                            resultDiv.className = 'mt-3 alert alert-danger';
                            resultDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                        }
                    })
                    .catch(error => {
                        resultDiv.style.display = 'block';
                        resultDiv.className = 'mt-3 alert alert-danger';
                        resultDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error de conexión: ' + error;
                    })
                    .finally(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Correo';
                    });
                });
                </script>
            </div>

            <!-- Orders Tab -->
            <div class="tab-pane fade show active" id="orders">
                <table id="ordersTable" class="table table-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Número de Orden</th>
                            <th>Cliente</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Total</th>
                            <th>Neto (Stripe)</th>
                            <th>MSI</th>
                            <th>Estado</th>
                            <th>Método de Pago</th>
                            <th>Código de Descuento</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['id']); ?></td>
                            <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($order['customer_email']); ?></td>
                            <td><?php echo htmlspecialchars($order['customer_phone']); ?></td>
                            <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                            <td>
                                <?php
                                    $netAmount = computeNetAmountForOrder($order);
                                ?>
                                <strong>$<?php echo number_format($netAmount, 2); ?></strong>
                            </td>
                            <td>
                                <?php
                                    $pid = isset($order['stripe_payment_intent_id']) ? (string)$order['stripe_payment_intent_id'] : '';
                                    $isInstallments = stripeUsesInstallments($pid);
                                ?>
                                <span class="badge bg-<?php echo $isInstallments ? 'success' : 'secondary'; ?> status-badge">
                                    <?php echo $isInstallments ? 'Sí' : 'No'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $order['status'] === 'completed' ? 'success' : ($order['status'] === 'pending' ? 'warning' : 'danger'); ?> status-badge">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($order['payment_method']); ?></td>
                            <td>
                                <?php if (!empty($order['discount_code'])): ?>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($order['discount_code']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'] . ' -6 hours')); ?></td>
                            <td>
                                <button class="btn btn-info btn-sm" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                    <i class="fas fa-eye"></i> Ver
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Customers Tab -->
            <div class="tab-pane fade" id="customers">
                <table id="customersTable" class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Dirección</th>
                            <th>Estado</th>
                            <th>Fecha de Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($customer['id']); ?></td>
                            <td><strong><?php echo htmlspecialchars($customer['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($customer['email']); ?></td>
                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                            <td><?php echo htmlspecialchars($customer['address']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $customer['status'] === 'active' ? 'success' : 'secondary'; ?> status-badge">
                                    <?php echo ucfirst($customer['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($customer['created_at'] . ' -6 hours')); ?></td>
                            <td>
                                <button class="btn btn-info btn-sm" onclick="viewCustomerDetails(<?php echo $customer['id']; ?>)">
                                    <i class="fas fa-eye"></i> Ver
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Payments Tab -->
            <div class="tab-pane fade" id="payments">
                <table id="paymentsTable" class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Número de Orden</th>
                            <th>Cliente</th>
                            <th>Email</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th>Método de Pago</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payment['id']); ?></td>
                            <td><strong><?php echo htmlspecialchars($payment['order_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($payment['customer_email']); ?></td>
                            <td><strong>$<?php echo number_format($payment['amount'], 2); ?></strong></td>
                            <td>
                                <span class="badge bg-<?php echo $payment['status'] === 'succeeded' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'danger'); ?> status-badge">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($payment['payment_method_type']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($payment['created_at'] . ' -6 hours')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Newsletter Tab -->
            <div class="tab-pane fade" id="newsletter">
                <table id="subscribersTable" class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Email</th>
                            <th>Fecha de Suscripción</th>
                            <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscribers as $subscriber): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subscriber['id']); ?></td>
                                <td><?php echo htmlspecialchars($subscriber['email']); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($subscriber['created_at'] . ' -6 hours')); ?></td>
                                <td>
                                    <button class="btn btn-danger btn-sm delete-btn" data-type="subscriber" data-id="<?php echo htmlspecialchars($subscriber['id']); ?>">
                                    <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                        <!-- Forms Tab -->
            <div class="tab-pane fade" id="forms">
                <table id="formsTable" class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                            <th>Nombre</th>
                            <th>Apellido</th>
                                <th>Email</th>
                            <th>Teléfono</th>
                            <th>Mensaje</th>
                            <th>Fecha de Envío</th>
                            <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($form_submissions as $submission): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($submission['id']); ?></td>
                            <td><strong><?php echo htmlspecialchars($submission['nombre']); ?></strong></td>
                                <td><?php echo htmlspecialchars($submission['apellido']); ?></td>
                                <td><?php echo htmlspecialchars($submission['correo']); ?></td>
                            <td><?php echo htmlspecialchars($submission['numero']); ?></td>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?php echo htmlspecialchars($submission['mensaje']); ?>
                            </td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($submission['created_at'] . ' -6 hours')); ?></td>
                                <td>
                                    <button class="btn btn-danger btn-sm delete-btn" data-type="form" data-id="<?php echo htmlspecialchars($submission['id']); ?>">
                                    <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
            </div>

            <!-- Traffic Tab -->
            <div class="tab-pane fade" id="traffic">
                <div class="text-center">
                    <div class="mb-4">
                        <h4><i class="fas fa-chart-bar"></i> Tráfico ManyChat</h4>
                        <p class="text-muted">Análisis de tráfico de ManyChat con parámetros UTM.</p>
                    </div>
                    
                    <div class="text-center mb-4">
                        <a href="utm_links.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-link"></i> Ver Enlaces UTM
                        </a>
                        <a href="utm_analytics.php" class="btn btn-outline-info btn-sm ms-2">
                            <i class="fas fa-chart-line"></i> Gráficas UTM
                        </a>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title">Estadísticas de Tráfico</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($utm_stats)): ?>
                                        <div class="text-center py-3">
                                            <i class="fas fa-chart-pie fa-2x text-muted mb-2"></i>
                                            <p class="text-muted mb-0">No hay estadísticas de tráfico UTM aún</p>
                                        </div>
                                    <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Fuente de Tráfico</th>
                                                    <th>Visitas Únicas</th>
                                                    <th>Conversiones</th>
                                                    <th>Tasa de Conversión</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($utm_stats as $stat): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($stat['utm_source'] ?: 'Directo'); ?></td>
                                                    <td><?php echo htmlspecialchars($stat['unique_visits']); ?></td>
                                                    <td><?php echo htmlspecialchars($stat['conversions']); ?></td>
                                                    <td><?php echo htmlspecialchars($stat['conversion_rate']); ?>%</td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title">Productos Vendidos por Fuente</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($products_by_source)): ?>
                                        <div class="text-center py-3">
                                            <i class="fas fa-shopping-cart fa-2x text-muted mb-2"></i>
                                            <p class="text-muted mb-0">No hay datos de productos por fuente de tráfico</p>
                                        </div>
                                    <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Fuente de Tráfico</th>
                                                    <th>Producto</th>
                                                    <th>Vendidos</th>
                                                    <th>Cantidad Total</th>
                                                    <th>Ingreso Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($products_by_source as $source_data): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($source_data['traffic_source']); ?></td>
                                                    <td><?php echo htmlspecialchars($source_data['product_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($source_data['times_sold']); ?></td>
                                                    <td><?php echo htmlspecialchars($source_data['total_quantity']); ?></td>
                                                    <td><strong>$<?php echo number_format($source_data['total_revenue'], 2); ?></strong></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabla detallada de tráfico -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Detalles de Tráfico UTM</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($traffic_data)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No hay datos de tráfico UTM aún</h5>
                                    <p class="text-muted">Para rastrear el tráfico de ManyChat, asegúrate de que tus enlaces incluyan parámetros UTM como:</p>
                                    <div class="alert alert-warning d-inline-block">
                                        <code>?utm_source=manychat&utm_medium=whatsapp&utm_campaign=curso_finanzas</code>
                                    </div>
                                </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table id="trafficTable" class="table">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Fuente UTM</th>
                                            <th>Medio UTM</th>
                                            <th>Campaña UTM</th>
                                            <th>Cliente</th>
                                            <th>Email</th>
                                            <th>Orden</th>
                                            <th>Producto</th>
                                            <th>Total</th>
                                            <th>Estado</th>
                                            <th>IP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($traffic_data as $traffic): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($traffic['created_at'] . ' -6 hours')); ?></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo htmlspecialchars($traffic['utm_source'] ?: 'Directo'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo htmlspecialchars($traffic['utm_medium'] ?: 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo htmlspecialchars($traffic['utm_campaign'] ?: 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($traffic['customer_name']): ?>
                                                    <strong><?php echo htmlspecialchars($traffic['customer_name']); ?></strong>
                                                <?php else: ?>
                                                    <span class="text-muted">Visitante</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($traffic['customer_email']): ?>
                                                    <?php echo htmlspecialchars($traffic['customer_email']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($traffic['order_number']): ?>
                                                    <span class="badge bg-success">
                                                        <?php echo htmlspecialchars($traffic['order_number']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin orden</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($traffic['product_name']): ?>
                                                    <?php echo htmlspecialchars($traffic['product_name']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($traffic['total_amount']): ?>
                                                    <strong>$<?php echo number_format($traffic['total_amount'], 2); ?></strong>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($traffic['order_status']): ?>
                                                    <span class="badge bg-<?php echo $traffic['order_status'] === 'completed' ? 'success' : ($traffic['order_status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                        <?php echo ucfirst($traffic['order_status']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?php echo htmlspecialchars($traffic['ip_address'] ?: 'N/A'); ?></small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- QR Tab -->
            <div class="tab-pane fade" id="qr">
                <div class="text-center">
                    <div class="mb-4">
                        <h4><i class="fas fa-qrcode"></i> Generador de Código QR</h4>
                        <p class="text-muted">Genera un código QR que lleva directamente al carrito con el producto "Los 6 pasos para tu Independencia Financiera"</p>
                    </div>
                    
                    <a href="generate_qr.php" class="btn btn-outline-primary btn-md">
                        <i class="fas fa-qrcode"></i> Generar QR
                    </a>
                    
                    <div class="mt-4">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> ¿Cómo funciona?</h6>
                            <ul class="text-start mb-0">
                                <li>Haz clic en "Generar Código QR del Carrito"</li>
                                <li>Se creará un código QR personalizado</li>
                                <li>Los clientes pueden escanearlo con cualquier app de QR</li>
                                <li>Serán llevados directamente al carrito con el producto</li>
                                <li>El producto se agregará automáticamente</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Webinar Tab -->
            <div class="tab-pane fade" id="webinar">
                <div class="text-center">
                    <div class="mb-4 position-relative">
                        <h4><i class="fas fa-video"></i> Webinar</h4>
                        <p class="text-muted">Gestión básica de webinars y enlaces de transmisión.</p>
                        
                        <!-- Version Selector - Arriba a la derecha -->
                        <div class="webinar-version-selector">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle webinar-version-btn" type="button" id="webinarVersionDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-database"></i>
                                    <span class="version-text"><?php echo $webinar_version === 'v1' ? 'Versión 1' : 'Versión 2'; ?></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="webinarVersionDropdown">
                                    <li>
                                        <a class="dropdown-item <?php echo $webinar_version === 'v1' ? 'active' : ''; ?>" href="?<?php echo http_build_query(array_merge($_GET, ['webinar_version' => 'v1'])); ?>#webinar-tab">
                                            <i class="fas fa-database text-primary"></i> Versión 1
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item <?php echo $webinar_version === 'v2' ? 'active' : ''; ?>" href="?<?php echo http_build_query(array_merge($_GET, ['webinar_version' => 'v2'])); ?>#webinar-tab">
                                            <i class="fas fa-database text-success"></i> Versión 2
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row mb-4 text-start">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="card h-100">
                                <div class="card-header bg-white">
                                    <h5 class="card-title mb-0 text-primary"><i class="fas fa-map-marker-alt"></i> Registros por Estado</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="stateChart" style="max-height: 300px;"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-white">
                                    <h5 class="card-title mb-0 text-primary"></i>Red Social</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="sourceChart" style="max-height: 300px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title">Registro de Webinar</h5>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#webinarLinksModal">
                                    <i class="fas fa-link"></i> Links
                                </button>
                                <a href="dashboard.php?export=webinar&webinar_version=<?php echo $webinar_version; ?>#webinar-tab" class="btn btn-success btn-sm">
                                    <i class="fas fa-file-excel"></i> Descargar Excel
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="webinarTable" class="table">
                                    <thead>
                                        <tr>
                                            <th>Nombre completo</th>
                                            <th>Correo electrónico</th>
                                            <th>Número de teléfono</th>
                                            <th>Red Social</th>
                                            <th>Estado</th>
                                            <th>Fecha registro</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($webinars as $w): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($w['nombre_completo']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($w['correo_electronico']); ?></td>
                                            <td><?php echo htmlspecialchars($w['numero_telefono']); ?></td>
                                            <?php
                                                $source = strtolower(trim((string)($w['utm_source'] ?? '')));
                                                $sourceLabel = $source === '' ? 'Link Directo' : (in_array($source, ['ig', 'instagram', 'insta'], true) ? 'Instagram' : (string)($w['utm_source'] ?? ''));
                                                $lada = webinarExtractLada($w['numero_telefono'] ?? '');
                                                $state = webinarMexicoStateFromLada($lada);
                                                $stateLabel = $state === '' ? 'Sin Informacion' : $state;
                                            ?>
                                            <td><?php echo htmlspecialchars($sourceLabel); ?></td>
                                            <td><?php echo htmlspecialchars($stateLabel); ?></td>
                                            <td><small class="text-muted"><?php echo date('Y-m-d H:i:s', strtotime(($w['created_at'] ?? '') . ' -6 hours')); ?></small></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mundial Tab -->
            <div class="tab-pane fade" id="mundial">
                <div class="text-center">
                    <!-- Gráfica de Registros por Estado -->
                    <?php if (!empty($registros_mundial)): ?>
                    <div class="row mb-4 text-start">
                        <div class="col-md-6 mx-auto">
                            <div class="card h-100">
                                <div class="card-header bg-white">
                                    <h5 class="card-title mb-0 text-primary"><i class="fas fa-map-marker-alt"></i> Registros por Estado</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="mundialStateChart" style="max-height: 300px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title"><i class="fas fa-list"></i> Lista de Registros</h5>
                            <a href="mundial_export.php" class="btn btn-success btn-sm">
                                <i class="fas fa-file-excel"></i> Descargar Excel
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($registros_mundial)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No hay registros aún</h5>
                                    <p class="text-muted">Los registros aparecerán aquí cuando se agreguen a la base de datos.</p>
                                </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table id="mundialTable" class="table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Correo Electrónico</th>
                                            <th>Teléfono</th>
                                            <th>Estado</th>
                                            <th>Fecha de Registro</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($registros_mundial as $r): ?>
                                        <?php
                                            $lada = webinarExtractLada($r['telefono'] ?? '');
                                            $estado = webinarMexicoStateFromLada($lada);
                                            $estadoLabel = $estado === '' ? 'Sin Información' : $estado;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['id']); ?></td>
                                            <td><strong><?php echo htmlspecialchars($r['nombre']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($r['correo']); ?></td>
                                            <td><?php echo htmlspecialchars($r['telefono'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($estadoLabel); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($r['fecha_registro'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="webinarLinksModal" tabindex="-1" aria-labelledby="webinarLinksModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="webinarLinksModalLabel">Links UTM Webinar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="mb-4">
                        <h6 class="mb-2">Meta (Facebook / Instagram)</h6>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">Facebook Ads</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=facebook&utm_medium=paid_social&utm_campaign=webinar_enero_2026&utm_content={{ad.name}}">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">Instagram Ads</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=instagram&utm_medium=paid_social&utm_campaign=webinar_enero_2026&utm_content={{ad.name}}">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">Facebook orgánico (post)</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=facebook&utm_medium=organic_social&utm_campaign=webinar_enero_2026&utm_content=post">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">Instagram orgánico (bio)</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=instagram&utm_medium=organic_social&utm_campaign=webinar_enero_2026&utm_content=bio">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">Instagram story</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=instagram&utm_medium=organic_social&utm_campaign=webinar_enero_2026&utm_content=story">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="mb-2">TikTok</h6>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">TikTok Ads</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=tiktok&utm_medium=paid_social&utm_campaign=webinar_enero_2026&utm_content={{ad.name}}">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">TikTok orgánico</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=tiktok&utm_medium=organic_social&utm_campaign=webinar_enero_2026&utm_content=video">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="mb-2">YouTube</h6>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">YouTube Ads</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=youtube&utm_medium=paid_video&utm_campaign=webinar_enero_2026&utm_content={{ad.name}}">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">YouTube orgánico (descripción)</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=youtube&utm_medium=organic_video&utm_campaign=webinar_enero_2026&utm_content=description">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="mb-2">Google</h6>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">Google Search Ads</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=google&utm_medium=cpc&utm_campaign=webinar_enero_2026&utm_term={keyword}&utm_content={creative}">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">Google orgánico</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=google&utm_medium=organic&utm_campaign=webinar_enero_2026">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="mb-2">WhatsApp</h6>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">WhatsApp (broadcast/lista)</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=whatsapp&utm_medium=message&utm_campaign=webinar_enero_2026&utm_content=broadcast">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">WhatsApp (grupo)</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=whatsapp&utm_medium=group&utm_campaign=webinar_enero_2026&utm_content=group">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="mb-2">Email / SMS</h6>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">Email (newsletter)</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=email&utm_medium=newsletter&utm_campaign=webinar_enero_2026&utm_content=correo_1">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">Email (automation)</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=email&utm_medium=automation&utm_campaign=webinar_enero_2026&utm_content=secuencia_1">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">SMS</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=sms&utm_medium=message&utm_campaign=webinar_enero_2026&utm_content=sms_1">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="mb-2">LinkedIn</h6>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">LinkedIn Ads</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=linkedin&utm_medium=paid_social&utm_campaign=webinar_enero_2026&utm_content={{ad.name}}">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">LinkedIn orgánico</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=linkedin&utm_medium=organic_social&utm_campaign=webinar_enero_2026&utm_content=post">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="mb-2">X (Twitter)</h6>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">X Ads</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=x&utm_medium=paid_social&utm_campaign=webinar_enero_2026&utm_content={{ad.name}}">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">X orgánico</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=x&utm_medium=organic_social&utm_campaign=webinar_enero_2026&utm_content=tweet">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <h6 class="mb-2">Telegram / Messenger</h6>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">Telegram</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=telegram&utm_medium=message&utm_campaign=webinar_enero_2026&utm_content=canal">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="fw-semibold mb-1">Messenger</div>
                            <div class="input-group">
                                <input type="text" class="form-control" readonly value="https://webinar.capitanfinanciero.com/?utm_source=messenger&utm_medium=message&utm_campaign=webinar_enero_2026&utm_content=inbox">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebinarLink(this)"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para detalles de orden -->
    <div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderModalLabel">Detalles de la Orden</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="orderModalBody">
                    <!-- El contenido se cargará dinámicamente -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para detalles de cliente -->
    <div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="customerModalLabel">Detalles del Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="customerModalBody">
                    <!-- El contenido se cargará dinámicamente -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Espacio adicional en la parte inferior -->
    <div style="height: 3rem;"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Charts Data
        const stateData = <?php echo json_encode($stateCounts); ?>;
        const sourceData = <?php echo json_encode($sourceCounts); ?>;
        const mundialStateData = <?php echo json_encode($mundialStateCounts); ?>;

        // Render Charts when document is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Mundial State Chart
            const mundialStateChartEl = document.getElementById('mundialStateChart');
            if (mundialStateChartEl) {
                const mundialStateCtx = mundialStateChartEl.getContext('2d');
                new Chart(mundialStateCtx, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(mundialStateData),
                        datasets: [{
                            label: 'Registros',
                            data: Object.values(mundialStateData),
                            backgroundColor: '#222F58',
                            borderColor: '#222F58',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }

            // State Chart
            const stateCtx = document.getElementById('stateChart').getContext('2d');
            new Chart(stateCtx, {
                type: 'bar',
                data: {
                    labels: Object.keys(stateData),
                    datasets: [{
                        label: 'Registros',
                        data: Object.values(stateData),
                        backgroundColor: '#222F58',
                        borderColor: '#222F58',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            // Source Chart
            const sourceCtx = document.getElementById('sourceChart').getContext('2d');
            new Chart(sourceCtx, {
                type: 'pie',
                data: {
                    labels: Object.keys(sourceData),
                    datasets: [{
                        data: Object.values(sourceData),
                        backgroundColor: [
                            '#E1306C', // Instagram
                            '#1877F2', // Facebook
                            '#000000', // TikTok / X
                            '#25D366', // WhatsApp
                            '#0A66C2', // LinkedIn
                            '#FF0000', // YouTube
                            '#6c757d', // Gray for others
                            '#ffc107', // Warning
                            '#17a2b8'  // Info
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        });

        // Mantener pestaña activa según el hash de la URL
        document.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash;
            if (hash === '#webinar-tab') {
                const webinarTab = document.querySelector('#webinar-tab');
                if (webinarTab) {
                    const tab = new bootstrap.Tab(webinarTab);
                    tab.show();
                }
            }
        });

        $(document).ready(function() {
            // Initialize DataTables
            $('#ordersTable').DataTable({
                order: [[0, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json'
                },
                pageLength: 10,
                responsive: true
            });
            $('#customersTable').DataTable({
                order: [[0, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json'
                },
                pageLength: 10,
                responsive: true
            });
            $('#paymentsTable').DataTable({
                order: [[0, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json'
                },
                pageLength: 10,
                responsive: true
            });
            $('#subscribersTable').DataTable({
                order: [[0, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json'
                },
                pageLength: 10,
                responsive: true
            });
            $('#formsTable').DataTable({
                order: [[0, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json'
                },
                pageLength: 10,
                responsive: true
            });
            $('#webinarTable').DataTable({
                order: [[5, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json'
                },
                pageLength: 10,
                responsive: true
            });
            $('#mundialTable').DataTable({
                order: [[0, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json'
                },
                pageLength: 10,
                responsive: true
            });
            
            <?php if (!empty($traffic_data)): ?>
            $('#trafficTable').DataTable({
                order: [[0, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json'
                },
                pageLength: 10,
                responsive: true
            });
            <?php endif; ?>
        });

        // Delete functionality
        $('.delete-btn').on('click', function() {
            const id = $(this).data('id');
            const type = $(this).data('type');
            const confirmMessage = type === 'subscriber' ? 
                '¿Estás seguro de que deseas eliminar este suscriptor?' : 
                '¿Estás seguro de que deseas eliminar esta presentación de formulario?';

            if (confirm(confirmMessage)) {
                $.ajax({
                    url: 'delete.php',
                    method: 'POST',
                    data: { id: id, type: type },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error al procesar la solicitud');
                    }
                });
            }
        });

        // View order details
        function viewOrderDetails(orderId) {
            // Mostrar loading en el modal
            $('#orderModalBody').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Cargando detalles...</p></div>');
            $('#orderModal').modal('show');
            
            // Cargar detalles de la orden
            $.ajax({
                url: 'get_order_details.php',
                method: 'POST',
                data: { order_id: orderId },
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        $('#orderModalBody').html('<div class="alert alert-danger">Error: ' + response.error + '</div>');
                    } else {
                        displayOrderDetails(response);
                    }
                },
                error: function() {
                    $('#orderModalBody').html('<div class="alert alert-danger">Error al cargar los detalles de la orden</div>');
                }
            });
        }

        // Display order details in modal
        function displayOrderDetails(data) {
            const order = data.order;
            const items = data.items;
            const payment = data.payment;
            
            const discountCents = order.discount_amount_cents ? parseInt(order.discount_amount_cents, 10) : null;
            const discountAmount = discountCents ? (discountCents / 100) : (order.discount_amount ? parseFloat(order.discount_amount) : null);
            const discountCode = order.discount_code || '';
            const totalCents = order.total_amount_cents ? parseInt(order.total_amount_cents, 10) : Math.round(parseFloat(order.total_amount) * 100);
            const subtotalCents = discountAmount != null ? (totalCents + Math.round(discountAmount * 100)) : null;
            
            let modalContent = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Información del Cliente</h6>
                        <div class="mb-3">
                            <strong>Nombre:</strong> ${order.customer_name}
                        </div>
                        <div class="mb-3">
                            <strong>Email:</strong> ${order.customer_email}
                        </div>
                        <div class="mb-3">
                            <strong>Teléfono:</strong> ${order.customer_phone}
                        </div>
                        <div class="mb-3">
                            <strong>Método de Pago:</strong> ${order.payment_method}
                        </div>
                        <div class="mb-3">
                            <strong>Código de Descuento:</strong> ${discountCode ? `<span class="badge bg-info">${discountCode}</span>` : '<span class="text-muted">N/A</span>'}
                        </div>
                        <div class="mb-3">
                            <strong>Descuento Aplicado:</strong> ${discountAmount != null ? `<span class="text-success">-$${discountAmount.toFixed(2)} MXN</span>` : '<span class="text-muted">N/A</span>'}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Información de la Orden</h6>
                        <div class="mb-3">
                            <strong>Número de Orden:</strong> ${order.order_number}
                        </div>
                        <div class="mb-3">
                            <strong>Fecha:</strong> ${new Date(order.created_at).toLocaleDateString('es-ES')}
                        </div>
                        <div class="mb-3">
                            <strong>Estado:</strong> 
                            <span class="badge bg-${order.status === 'completed' ? 'success' : (order.status === 'pending' ? 'warning' : 'danger')}">
                                ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                            </span>
                        </div>
                        <div class="mb-3">
                            <strong>Método de Pago:</strong> ${order.payment_method}
                        </div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <h6 class="text-muted mb-3">Productos Comprados</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Precio</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            items.forEach(item => {
                // Usar unit_price_cents si está disponible, sino unit_price
                const price = item.product_price_cents ? (item.product_price_cents / 100) : parseFloat(item.product_price);
                
                modalContent += `
                    <tr>
                        <td><strong>${item.product_name}</strong></td>
                        <td>${item.product_description || 'Sin descripción'}</td>
                        <td>${item.quantity}</td>
                        <td><strong>$${price.toFixed(2)} MXN</strong></td>
                    </tr>
                `;
            });
            
            modalContent += `
                        </tbody>
                    </table>
                </div>
                
                <div class="text-end" style="margin-top: 0.5rem;">
                    ${discountAmount != null ? `
                        <div style="font-size: 0.95rem;">
                            <span>Subtotal:</span> <strong>$${(subtotalCents / 100).toFixed(2)} MXN</strong>
                        </div>
                        <div style="font-size: 0.95rem; color: #28a745;">
                            <span>Descuento:</span> <strong>-$${discountAmount.toFixed(2)} MXN</strong>
                        </div>
                    ` : ''}
                    <h5>Total: <strong>$${(order.total_amount_cents / 100).toFixed(2)} MXN</strong></h5>
                </div>
            `;
            
            if (payment) {
                modalContent += `
                    <hr class="my-4">
                    <h6 class="text-muted mb-3">Información del Pago</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-2">
                                <strong>ID de Pago:</strong> ${payment.stripe_payment_intent_id}
                            </div>
                            <div class="mb-2">
                                <strong>Estado:</strong> 
                                <span class="badge bg-${payment.status === 'succeeded' ? 'success' : (payment.status === 'pending' ? 'warning' : 'danger')}">
                                    ${payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <strong>Método:</strong> ${payment.payment_method_type}
                            </div>
                            <div class="mb-2">
                                <strong>Fecha:</strong> ${new Date(payment.created_at).toLocaleDateString('es-ES')}
                            </div>
                        </div>
                    </div>
                `;
            }
            
            $('#orderModalBody').html(modalContent);
        }

        // View customer details
        function viewCustomerDetails(customerId) {
            // Mostrar loading en el modal
            $('#customerModalBody').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Cargando detalles...</p></div>');
            $('#customerModal').modal('show');
            
            // Cargar detalles del cliente
            $.ajax({
                url: 'get_customer_details.php',
                method: 'POST',
                data: { customer_id: customerId },
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        $('#customerModalBody').html('<div class="alert alert-danger">Error: ' + response.error + '</div>');
                    } else {
                        displayCustomerDetails(response);
                    }
                },
                error: function() {
                    $('#customerModalBody').html('<div class="alert alert-danger">Error al cargar los detalles del cliente</div>');
                }
            });
        }

        // Display customer details in modal
        function displayCustomerDetails(data) {
            const customer = data.customer;
            const orders = data.orders;
            const stats = data.stats;
            const products = data.products;
            
            let modalContent = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Información del Cliente</h6>
                        <div class="mb-3">
                            <strong>Nombre:</strong> ${customer.name}
                        </div>
                        <div class="mb-3">
                            <strong>Email:</strong> ${customer.email}
                        </div>
                        <div class="mb-3">
                            <strong>Teléfono:</strong> ${customer.phone}
                        </div>
                        <div class="mb-3">
                            <strong>Dirección:</strong> ${customer.address}
                        </div>
                        <div class="mb-3">
                            <strong>Estado:</strong> 
                            <span class="badge bg-${customer.status === 'active' ? 'success' : 'secondary'}">
                                ${customer.status.charAt(0).toUpperCase() + customer.status.slice(1)}
                            </span>
                        </div>
                        <div class="mb-3">
                            <strong>Fecha de Registro:</strong> ${new Date(customer.created_at).toLocaleDateString('es-ES')}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Estadísticas de Compras</h6>
                        <div class="mb-3">
                            <strong>Total Gastado:</strong> <span class="text-success">$${parseFloat(stats.total_spent).toFixed(2)}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Órdenes Completadas:</strong> ${stats.total_orders || 0}
                        </div>
                        <div class="mb-3">
                            <strong>Valor Promedio por Orden:</strong> $${stats.average_order_value ? parseFloat(stats.average_order_value).toFixed(2) : '0.00'}
                        </div>
                        <div class="mb-3">
                            <strong>Primera Compra:</strong> ${stats.first_order_date ? new Date(stats.first_order_date).toLocaleDateString('es-ES') : 'N/A'}
                        </div>
                        <div class="mb-3">
                            <strong>Última Compra:</strong> ${stats.last_order_date ? new Date(stats.last_order_date).toLocaleDateString('es-ES') : 'N/A'}
                        </div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <h6 class="text-muted mb-3">Productos Comprados</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Descripción</th>
                                <th>Cantidad Total</th>
                                <th>Veces Comprado</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            if (products.length > 0) {
                products.forEach(product => {
                    modalContent += `
                        <tr>
                            <td><strong>${product.product_name}</strong></td>
                            <td>${product.product_description || 'Sin descripción'}</td>
                            <td>${product.total_quantity}</td>
                            <td>${product.times_purchased}</td>
                        </tr>
                    `;
                });
            } else {
                modalContent += `
                    <tr>
                        <td colspan="4" class="text-center text-muted">No ha comprado productos aún</td>
                    </tr>
                `;
            }
            
            modalContent += `
                        </tbody>
                    </table>
                </div>
                
                <hr class="my-4">
                
                <h6 class="text-muted mb-3">Historial de Órdenes</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Número de Orden</th>
                                <th>Fecha</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Items</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            if (orders.length > 0) {
                orders.forEach(order => {
                    modalContent += `
                        <tr>
                            <td><strong>${order.order_number}</strong></td>
                            <td>${new Date(order.created_at).toLocaleDateString('es-ES')}</td>
                            <td><strong>$${parseFloat(order.total_amount).toFixed(2)}</strong></td>
                            <td>
                                <span class="badge bg-${order.status === 'completed' ? 'success' : (order.status === 'pending' ? 'warning' : 'danger')}">
                                    ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                                </span>
                            </td>
                            <td>${order.total_items || 0}</td>
                        </tr>
                    `;
                });
            } else {
                modalContent += `
                    <tr>
                        <td colspan="5" class="text-center text-muted">No tiene órdenes aún</td>
                    </tr>
                `;
            }
            
            modalContent += `
                        </tbody>
                    </table>
                </div>
            `;
            
            $('#customerModalBody').html(modalContent);
        }

        async function copyWebinarLink(button) {
            const input = button.closest('.input-group')?.querySelector('input');
            const text = input ? input.value : '';
            if (!text) return;

            try {
                await navigator.clipboard.writeText(text);
            } catch (e) {
                if (input) {
                    input.focus();
                    input.select();
                    document.execCommand('copy');
                    input.setSelectionRange(0, 0);
                    input.blur();
                }
            }

            const icon = button.querySelector('i');
            if (icon) {
                const original = icon.className;
                icon.className = 'fas fa-check';
                setTimeout(() => {
                    icon.className = original;
                }, 900);
            }
        }
    </script>
</body>
</html>
