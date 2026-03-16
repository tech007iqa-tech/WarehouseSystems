<?php
// debug_api_call.php
$url = 'http://localhost/api/add_label.php';
$data = [
    'brand' => 'HP',
    'model' => 'EliteBook',
    'series' => '840 G3',
    'cpu_gen' => 'i5 8th Gen',
    'cpu_specs' => 'i5-8350U',
    'cpu_cores' => '4',
    'cpu_speed' => '1.70',
    'has_ram' => '1',
    'ram' => '8GB',
    'battery' => '1',
    'bios_state' => 'Unknown',
    'description' => 'Untested',
    'warehouse_location' => 'Shelf A1'
];

$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data),
        'ignore_errors' => true
    ]
];
$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);

echo "Response Headers:\n";
print_r($http_response_header);
echo "\nResponse Body:\n";
echo $result;
?>
