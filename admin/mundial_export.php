<?php
session_start();
require_once('../config.php');

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Funciones para extraer estado desde el teléfono
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

try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->query("SELECT * FROM registros ORDER BY fecha_registro DESC");
    $registros = $stmt->fetchAll();
    
    $filename = 'registros_mundial_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo "\xEF\xBB\xBF";
    echo "sep=;\n";
    $out = fopen('php://output', 'w');
    
    fputcsv($out, ['ID', 'Nombre', 'Correo', 'Teléfono', 'Estado', 'Fecha de Registro'], ';');
    foreach ($registros as $r) {
        $lada = webinarExtractLada($r['telefono'] ?? '');
        $estado = webinarMexicoStateFromLada($lada);
        $estadoLabel = $estado === '' ? 'Sin Información' : $estado;
        
        fputcsv($out, [
            $r['id'],
            $r['nombre'],
            $r['correo'],
            $r['telefono'] ?? '',
            $estadoLabel,
            $r['fecha_registro']
        ], ';');
    }
    fclose($out);
    exit;
    
} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}
