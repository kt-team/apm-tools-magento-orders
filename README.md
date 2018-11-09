### Сбор метрики Отмененные заказы с Magento (с отставанием в 2 дня)

Для подключения необходимо:
1. Скачать скрипт в нужную директорию на сервере  
    `git clone https://github.com/kt-team/apm-tools-magento-orders.git`

2. Произвести настройки скрипта  
    Настройки скрипта находятся в файле config.php.
    Описание основных параметров ниже.

3. Настроить запуск скрипта  
    Запуск скрипта можно настроить по средствам cron раз в день.  
    Например, `10 3 * * * php /srv/local/apm/apm-tools-magento-orders/orders.php`


##### Основные параметры скрипта:
- **$config['license_key']** - Ключ выданный сервисом APMinvest
- **$config['apm_canceled_orders']** - Настройки для APMinvest
    - **['metric_code']** - Код метрики в APMinvest
    - **['metric_label']** - Лейбл метрики в APMinvest
- **$config['magento_query_params']** - Параметры для запроса отмененных заказов Magento
    - **['magento_endpoint_orders']** - REST Api Endpoint Magento для получения заказов
    - **['auth_token']** - Токен для авторизации в мадженте
    - **['status']** - Статус заказов

