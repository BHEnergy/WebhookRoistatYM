<?php


//Подключение библиотеки / Connect lib
include_once('crest.php');

//Получение данных из источников / Get source of Data
$source = $_GET['source'] ?? '';

//Получение данных / Get Data
$inputData = trim(file_get_contents('php://input'));

//Декдоирование данных / Decode Data
$data = json_decode($inputData, true);

//Первое логирование / First Log
file_put_contents('...', json_encode($data, JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);

//Начало обработки / Start of Script
if($source === 'roistat') {
    
    //Получение данных из Roistat / Get Data from Roistat
    $roistatData = [
        'caller' => $data['caller'] ?? null,
        'callee' => $data['callee'] ?? null,
        'metrika_client_id' => $data['metrika_client_id'] ?? null,
        'call_date' => $data['date'] ?? null,
    ];

    //Логирование данных из Roistat / Loged Data from Roistat
    file_put_contents('...', json_encode($roistatData, JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
    
    //Проверка телефона / Check Phone
    $phone = "+{$roistatData['caller']}";
    
    //Логирование телефона / Loged Phone
    file_put_contents('...', " Номер телефона: {$phone} \n", FILE_APPEND);
    
    //Ожидание лида / Wait Lead
    sleep (10);
    
    //Получение лидов / Get All Leads
    $result = CRest::call(
        'crm.lead.list',
        [
            'filter' => ['PHONE' => $phone],
            'select' => ['ID', 'TITLE', 'DATE_CREATE', 'PHONE', 'UF_CRM_1715160961436']
        ]
    );
    
    //Ожидание / Wait
    sleep (10);
    
    //Логирование лидов / Log Lead
    file_put_contents('...', "Leads Found: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    
    //Проверка на лиды / Check Leads
    if(isset($result['result']) && is_array($result['result'])) {
        $leads = $result['result'];
        
        $roistatDateTime = new DateTime($roistatData['call_date']);
        $roistatDateTime->setTimezone(new DateTimeZone('UTC'));
        
        
        $batchCommands = [];
        
        sleep (10);
        
        //Перебор лидов / Foreach in array of leads
        foreach($leads as $lead) {
            //Проверка времени лида / Check lead time
            $leadDateTime = new DateTime($lead['DATE_CREATE']);
            $leadDateTime->setTimezone(new DateTimeZone('UTC'));
            
            $timeDifference = abs($leadDateTime->getTimestamp() - ($roistatDateTime->getTimestamp() + 3 * 3600));
            
            $fixData = $roistatDateTime->getTimestamp() + (3 * 3600);
            
            //TimeDifference
            file_put_contents('...', "Время лида: {$leadDateTime->getTimestamp()} - Время от ройстата: {$fixData} = Разница: {$timeDifference}\n", FILE_APPEND);
            
            //Логирование лида / Log Lead
            file_put_contents('...', "Номер лида: " . $lead['ID'] . ". Разница между лидом и звонком: " . $timeDifference . ".\n Лид: " . json_encode($lead) ."\n", FILE_APPEND);
        
            //Check Lead
            if(str_contains($lead['TITLE'], 'Входящий звонок') && $timeDifference <= 600 && !isset($lead['UF_CRM_1715160961436'])) {
                //Формирование массива данных / Prepare Data
                $params = [
                    'ID' => $lead['ID'],
                    'FIELDS' => [
                        'UF_CRM_1715160961436' => $roistatData['metrika_client_id']  
                    ]
                ];
                
                //Вызов команды / Call command
                $batchCommands[] = [
                    'method' => 'crm.lead.update',
                    'params' => $params
                ];
                
            }
        }
        
        //Другая проверка / Another Check
        if (!empty($batchCommands)) {
            $batchResult = CRest::callBatch($batchCommands);

            file_put_contents('webhook-log-updated-lead.txt', "Batch Update Result: " . json_encode($batchResult) . "\n", FILE_APPEND);
        }
    }
}