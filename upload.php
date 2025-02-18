<?php

function createTicket($apiUrl, $ticketData)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/api/tickets');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($ticketData));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        'status_code' => $httpCode,
        'response' => json_decode($response, true)
    ];
}

function readCsvAndCreateTickets($csvFilePath, $apiUrl)
{
    if (!file_exists($csvFilePath) || !is_readable($csvFilePath)) {
        die("CSV file not found or not readable.");
    }

    $file = fopen($csvFilePath, 'r');
    $headers = fgetcsv($file); // Read header row

    while (($row = fgetcsv($file)) !== false) {
        $ticketData = array_combine($headers, $row);

        $ticket = [
            'project_id' => (int) $ticketData['project_id'],
            'status_id' => (int) $ticketData['status_id'],
            'priority_id' => (int) $ticketData['priority_id'],
            'type_id' => (int) $ticketData['type_id'],
            'responsible_id' => (int) $ticketData['responsible_id'],
            'owner_id' => (int) $ticketData['owner_id'],
            'content' => trim($ticketData['content']),
        ];

        $response = createTicket($apiUrl, $ticket);
        echo "Ticket created: " . json_encode($response) . "\n";
    }

    fclose($file);
}

// Example Usage
$apiUrl = 'https://controldesk.ncloud.africa'; // Replace with your API base URL


$response = readCsvAndCreateTickets('tickets.csv', $apiUrl);

echo "Status Code: " . $response['status_code'] . "\n";
print_r($response['response']);
