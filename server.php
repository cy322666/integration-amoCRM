<?php

require_once __DIR__ . '/app/bootstrap.php';

/** @var \app\config $config */

/* УСТАНОВКА ИНТЕГРАЦИИ */

$config->subdomain = '';
$config->client_id = '';
$config->client_secret = '';
$config->auth_code = '';
$config->redirect_url = '';

//\app::install($config);

/* ПОЛУЧЕНИЕ КЛИЕНТА ИНТЕГРАЦИИ */

$app = new \app;
$amoCRM = $app->getClient($config);

/* УСТАНОВКА ПЕРЕМЕННЫХ СЦЕНАРИЕВ*/

$config->task_text = 'Задача создана';
$config->completed_till_at = 'tomorrow';
$config->user_name = 'test';
$config->user_phone = 799999999;
$config->user_email = 'test@ya.ru';
$config->lead_name = 'Заявка';
$config->lead_tags = ['site', 'dev'];

$app->setConfig($config);

/* ЗАПУСК ЛОГИКИ */

if($amoCRM) $app->run($amoCRM);




