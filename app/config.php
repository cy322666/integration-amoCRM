<?php

namespace app;

class config
{
    public $pipeline_id = null;
    public $pipelines_ignore = [];
    public $status_id = null;

    public $responsible_id = null;
    public $responsible_users = [];
    public $control_double = true;

    public $create_task = true;
    public $task_text = 'Клиент оставил повторную заявку на сайте';
    public $completed_till_at = '15 minutes';//today

    public $create_note = true;
    public $note_text;

    public $distribution = false;

    public $user_name;
    public $user_phone;
    public $user_email;
    public $city;

    public $lead_name;
    public $lead_tags = [];
    public $form;
    public $custom_fields = [];
    public $set_utm = false;
    public $utm_content;
    public $utm_medium;
    public $utm_source;
    public $utm_campaign;
    public $utm_term;

    public $logger = true;
    public $cache = true;

    public $redirect_url = null;
    public $client_id = null;
    public $client_secret = null;
    public $subdomain = null;
    public $auth_code = null;
}
