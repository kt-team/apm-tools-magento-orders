<?php

$config = require_once __DIR__ . '/config.php';

if(APM_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

try {

    _log('Orders start');

    $dt = new \DateTime();
    $date = $dt->modify('-2 day')->format('Y-m-d');
    $config['apm_canceled_orders']['date'] = $date;

    $orderList = getMagentoOrders($config['magento_query_params']);
    if (!isset($orderList['total_count'])) {
        throw new \Exception('Неверный ответ от мадженты');
    }

    $orderList = excludeTestsOrders($orderList, $config['excluded_words'], $config['excluded_emails']);

    // Debug or Send
    if (APM_DEBUG) {
        echo 'Canceled orders: ' . $orderList['total_count'] . "\n";
    } else {
        sendToApm($orderList['total_count'], $config['apm_canceled_orders']);
        echo 'Done';
    }

    _log('Orders result: ' . $orderList['total_count']);

} catch(\Exception $e) {
    _log('Orders error: ' . $e->getMessage());
    echo $e->getMessage();
}



/**
 * @param array $params
 * @return mixed
 * @throws Exception
 */
function getMagentoOrders($params = [])
{
    $url = $params['magento_endpoint_orders'];

    $authToken = $params['auth_token'];

    // Время начала и конца. По умолчанию 2 дня назад.
    $dt = new \DateTime();
    $dt->modify('-2 days');
    $dateEndStr = $dt->format('Y-m-d') . ' 23:59:59';
    $dateStartStr = $dt->format('Y-m-d') . ' 00:00:00';

    // Переводим в UTC
    $dateEnd = new \DateTime($dateEndStr);
    $dateEnd = $dateEnd->setTimezone(new DateTimeZone('UTC'));
    $dateEnd = $dateEnd->format('Y-m-d H:i:s');
    $dateStart = new \DateTime($dateStartStr);
    $dateStart = $dateStart->setTimezone(new DateTimeZone('UTC'));
    $dateStart = $dateStart->format('Y-m-d H:i:s');

    $params = [
        'searchCriteria[filterGroups][0][filters][0][field]'            => 'status',
        'searchCriteria[filterGroups][0][filters][0][value]'            => $params['status'],
        'searchCriteria[filterGroups][1][filters][0][field]'            => 'created_at',
        'searchCriteria[filterGroups][1][filters][0][conditionType]'    => 'gteq',
        'searchCriteria[filterGroups][1][filters][0][value]'            => $dateStart,
        'searchCriteria[filterGroups][2][filters][0][field]'            => 'created_at',
        'searchCriteria[filterGroups][2][filters][0][conditionType]'    => 'lteq',
        'searchCriteria[filterGroups][2][filters][0][value]'            => $dateEnd,
        'searchCriteria[pageSize]' => 0,
        'searchCriteria[currentPage]' => 0
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 3);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 3);
    curl_setopt($ch, CURLOPT_HTTPGET, 1);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $authToken
    ]);

    $output = curl_exec($ch);
    $info = curl_getinfo($ch);
    if($info['http_code'] != 200 || !$output) {
        $res = json_decode($output, true);
        if(!empty($res['message'])) {
            throw new \Exception($res['message']);
        }
        throw new \Exception(sprintf('Не удалось получить заказы из мадженты (code: %s, без сообщения)', $info['http_code']));
    }

    return json_decode($output, true);
}

/**
 * @param $metric
 * @param array $apmSettings
 * @throws Exception
 */
function sendToApm($metric, array $apmSettings)
{
    $url = 'https://service.apminvest.com/metrics/stat/' . $apmSettings['metric_code'];

    $params = [
        'metrics' => $metric,
        'date' => $apmSettings['date'],
        'key' => $apmSettings['license_key'],
        'label' => $apmSettings['metric_label'],
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HTTPGET, 1);
    $output = curl_exec($ch);
    $info = curl_getinfo($ch);
    if($info['http_code'] != 200 || !$output) {
        throw new \Exception('К сожалению, не удалось отправить матрики в APMinvest. Проверьте, пожалуйста, параметры или обратитесь в тех. поддержку.');
    }
}

/**
 * Исключает тестовые заказы
 *      у которых в имени или фамилии найдется ТОЧНОЕ совпадение со словами из $excludedWords
 *      у которых почта будет совпадать с МАСКОЙ из $excludedEmails
 *
 * @param $data
 * @param array $excludedWords
 * @param array $excludedEmails
 * @return array
 */
function excludeTestsOrders(array $data, $excludedWords = [], $excludedEmails = [])
{
    $newList = [];

    if(isset($data['items']) && $excludedWords && $excludedEmails) {
        foreach($data['items'] as $key => $item) {

            // Проверяем имя
            $firstname = strtolower($item['customer_firstname']);
            if(in_array($firstname, $excludedWords)) {
                continue;
            }

            // Проверяем фамилию
            $lastname = strtolower($item['customer_lastname']);
            if(in_array($lastname, $excludedWords)) {
                continue;
            }

            // Проверяем почту
            $emailFind = false;
            foreach($excludedEmails as $email) {
                if(strpos($item['customer_email'], $email) !== false) {
                    $emailFind = true;
                    break;
                }
            }
            if($emailFind) {
                continue;
            }

            // Если прошли все проверки добавляем в новый список
            $newList[] = $item;
        }
    }

    $data['items'] = $newList;
    $data['total_count'] = count($newList);
    return $data;
}

/**
 * @param $msg
 */
function _log($msg) {
    global $config;
    $date = date('Y-m-d H:i:s');
    $msg = $date . ' - ' . $msg . PHP_EOL;
    if(!file_put_contents($config['log-file-path'], $msg, FILE_APPEND)) {
        echo $date . ' - Error file log';
    }
}
