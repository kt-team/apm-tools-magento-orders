<?php

// Лицензионный ключ полученный в сервисе APMinvest
$config['license_key'] = 'xxxx';

// Настройки для APM метрики
$config['apm_canceled_orders'] = [
    'license_key' => $config['license_key'],
    'metric_code' => 'canceled-orders',         // Код метрики
    'metric_label' => 'Отмененные заказы',      // Лейбл метрики
];

$config['magento_query_params'] = [
    // REST Api Endpoint Magento для получения заказов
    'magento_endpoint_orders' => 'https://___your_site___.ru/rest/default/V1/orders',
    'auth_token' => 'xxxxxxxx',     // Токен для авторизации в мадженте
    'status' => 'canceled'          // Статус заказов
];

// Тестовые заказы (регистронезависимо).
// По этим словам происходит поиск по точному совпадению в имени или фамилии клиента.
// При успешном совпадении заказ считается тестовым и исключается.
$config['excluded_words'] = ['automation', 'тест', 'test'];

// По этим словам происходит поиск по неточному сопадению в email клиента
// При успешном совпадении заказ считается тестовым и исключается.
$config['excluded_emails'] = ['@kt-team.de'];


// Режим отладки, вместо отправки данных в APMInvest, просто выводит их
const APM_DEBUG = false;

// Путь до лог файла
$config['log-file-path'] = __DIR__ . '/log.log';

date_default_timezone_set('Europe/Moscow');

return $config;