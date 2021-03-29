<?php

use app\config;
use Ufee\Amo\Oauthapi;

class app
{
    private $config;
    private $amoCRM;

    public static function install(config $config)
    {
        if ($config->subdomain     != null &&
            $config->client_secret != null &&
            $config->client_id     != null &&
            $config->redirect_url  != null) {

            $oauth = self::auth($config);

            echo '<pre>'; print_r($oauth); echo '</pre>';

            return $oauth;
        } else {
            echo 'params config not full';

            exit;
        }
    }

//    public function setConfig(config $config)
//    {
//        $this->config = $config;
//    }

    public static function getClient(config $config)
    {
        if($config->logger) self::inputLog();

        $amoCRM = Oauthapi::setInstance([
            'domain' => $config->subdomain,
            'client_id' => $config->client_id,
            'client_secret' => $config->client_secret,
            'redirect_uri' => $config->redirect_url,
        ]);
        
        $amoCRM = \Ufee\Amo\Oauthapi::getInstance($config->client_id);

        $amoCRM->queries->logs(__DIR__.'/storage/logs');
        $amoCRM->queries->setDelay(1);
        $amoCRM->queries->cachePath(__DIR__.'/storage/cache');

        \Ufee\Amo\Services\Account::setCacheTime(3600);

        return $amoCRM;
    }

    public function setConfig($config)
    {
        $this->config = $config;
    }

    private static function inputLog()
    {
        $fw = fopen(__DIR__.'/storage/logs/input.log', "a");
        fwrite($fw, print_r(date('d-m-Y H:i:s'), true));
        fwrite($fw, print_r($_REQUEST, true));
        fclose($fw);
    }

    private static function auth(config $config)
    {
        $amoCRM = Oauthapi::setInstance([
            'domain' => $config->subdomain,
            'client_id' => $config->client_id,
            'client_secret' => $config->client_secret,
            'redirect_uri' => $config->redirect_url,
        ]);

        $oauth = $amoCRM->fetchAccessToken($config->auth_code);

        return $oauth;
    }

    public function run($amoCRM)
    {
        $this->amoCRM = $amoCRM;

        $contact = $this->searchContact();

        //print_r($this->config); exit;

        if($contact == null) $contact = $this->createContact();
        else
            $contact = $this->updateContact($contact);

        if($this->config->control_double)
            $lead = $this->searchLead($contact);

        if($lead == null) $lead = $this->createLead($contact);
        else
            $lead = $this->updateLead($lead);

        if($this->config->create_task) $this->createTask($lead);

        if($this->config->create_note) $this->createNote($lead);
    }

    private function searchContact()
    {
        if($this->config->user_phone)
            $contacts = $this->amoCRM
                ->contacts()
                ->searchByPhone($this->config->user_phone);

        if($contacts == null || $contacts->first() == null)
            if($this->config->user_email)
                $contacts = $this->amoCRM
                    ->contacts()
                    ->searchByEmail($this->config->user_email);

        if($contacts == null || $contacts->first() == null)
            return null;
        else
            return $contacts->first();
    }

    private function updateContact($contact)
    {
        if($this->config->phone)
            $contact->cf('Телефон')->setValue($this->config->user_phone);
        if($this->config->email)
            $contact->cf('Email')->setValue($this->config->user_email);
        if($this->config->city)
            $contact->cf('Телефон')->setValue($this->config->city);

        if($this->config->contact_custom_fields) {

            foreach ($this->config->contact_custom_fields as $key => $field) {

                $contact->cf($key)->setValue($field);
            }
        }

        $contact->save();

        return $contact;
    }

    private function createContact()
    {
        $contact = $this->amoCRM->contacts()->create();

        $contact->name = $this->config->user_name ? $this->config->user_name : 'Неизвестно';

        if($this->config->user_phone)
            $contact->cf('Телефон')->setValue($this->config->user_phone);
        if($this->config->user_email)
            $contact->cf('Email')->setValue($this->config->user_email);
        if($this->config->city)
            $contact->cf('Город')->setValue($this->config->city);

        if($this->config->contact_custom_fields) {

            foreach ($this->config->contact_custom_fields as $key => $field) {

                $contact->cf($key)->setValue($field);
            }
        }

        $contact->save();

        return $contact;
    }

    private function searchLead($contact)
    {
        if($contact->leads) {

            $arrayLeads = $contact->leads->toArray();

            foreach ($arrayLeads as $arrayLead) {

                if ($arrayLead['status_id'] != 143 &&
                    $arrayLead['status_id'] != 142) {

                        if($this->config->pipeline_ignore[0]) {

                            if($this->checkPipelineIgnore($arrayLead))
                                continue;
                        }

                        $lead = $this->amoCRM
                            ->leads()
                            ->find($arrayLead['id']);
                        return $lead;
                    }
                }
            }
        }

    private function checkPipelineIgnore($arrayLead)
        {
            foreach ($this->config->pipeline_ignore as $pipeline) {

                if($arrayLead['pipeline_id'] == $pipeline);
                    return true;
            }
        }

    private function createLead($contact)
    {
        $lead = $this->amoCRM->leads()->create();

        $lead->name = $this->config->lead_name ? $this->config->lead_name : 'Заявка с сайта';

        if ($this->config->lead_tags[0])
            $lead->attachTags($this->config->lead_tags);

        if($this->config->responsible_id)
            $lead->responsible_user_id = $this->config->responsible_id;

        elseif($this->config->distribution) {
            $responsible_id = $this->getResponsibleDistribution();

            $lead->responsible_user_id = $responsible_id;
        } else
            $lead->responsible_user_id = $contact->responsible_user_id;

        if($this->config->pipeline_id) {

            $lead->pipeline_id = $this->config->pipeline_id;
        }

        if($this->config->status_id) {

            $lead->status_id = $this->config->status_id;
        }

        if($this->config->form) {
            $status_id = \helper::switchForm($this->config->form);

            $lead->status_id = $status_id;
        }

        if($this->config->set_utm) {
            $lead->cf('utm_content')->setValue($this->config->utm_content);
            $lead->cf('utm_medium')->setValue($this->config->utm_medium);
            $lead->cf('utm_source')->setValue($this->config->utm_source);
            $lead->cf('utm_campaign')->setValue($this->config->utm_campaign);
            $lead->cf('utm_term')->setValue($this->config->utm_term);
        }

        if($this->config->lead_custom_fields) {

            foreach ($this->config->lead_custom_fields as $key => $field) {

                $lead->cf($key)->setValue($field);
            }
        }

        $lead->contacts_id = $contact->id;

        $lead->save();

        return $lead;
    }

    private function updateLead($lead)
    {
        $lead->name = $this->config->leadname ? $this->config->leadname : 'Заявка с сайта';

        if ($this->config->lead_tags[0])
            $lead->attachTags($this->config->lead_tags);

        if($this->config->responsible_id)
            $lead->responsible_user_id = $this->config->responsible_id;

        elseif($this->config->distribution) {
            $responsible_id = $this->getResponsibleDistribution();

            $lead->responsible_user_id = $responsible_id;
        }

        if($this->config->pipeline_id) {

            $lead->pipeline_id = $this->config->pipeline_id;
        }

        if($this->config->status_id) {

            $lead->status_id = $this->config->status_id;
        }

        if($this->config->form) {
            $status_id = \helper::switchForm($this->config->form);

            $lead->status_id = $status_id;
        }

        if($this->config->set_utm) {
            $lead->cf('utm_content')->setValue($this->config->utm_content);
            $lead->cf('utm_medium')->setValue($this->config->utm_medium);
            $lead->cf('utm_source')->setValue($this->config->utm_source);
            $lead->cf('utm_campaign')->setValue($this->config->utm_campaign);
            $lead->cf('utm_term')->setValue($this->config->utm_term);
        }

        if($this->config->lead_custom_fields) {

            foreach ($this->config->lead_custom_fields as $key => $field) {

                $lead->cf($key)->setValue($field);
            }
        }

        $lead->save();

        return $lead;
    }

    private function createTask($lead)
    {
        if($lead) {
            $task = $lead->createTask($type = 1);

            $task->text = $this->config->task_text;
            $task->element_type = 2;
            $task->responsible_user_id = $lead->responsible_user_id;
            $task->complete_till_at = strtotime($this->config->completed_till_at);
            $task->element_id = $lead->id;
            $task->save();

            return $task;
        } else
            echo 'lead for create task not found';
    }

    private function createNote($lead)
    {
        //$text_cf = \helper::createTextNote($this->config->custom_fields);

        $array = [
            'Новая заявка с сайта',
            '----------------------',
            ' - Имя : '.$this->config->user_name,
            ' - Телефон : '.$this->config->user_phone,
            ' - Почта : '.$this->config->user_email,
            '----------------------',
        ];

        if($this->config->note_text) $text = array_merge($array, $this->config->note_text);

        $text = implode("\n", $text);

        $note = $this->amoCRM->notes()->create();
        $note->note_type = 4;
        $note->text = $text;
        $note->element_type = 2;
        $note->element_id = $lead->id;
        $note->save();

        return $note;
    }

    private function getResponsibleDistribution()
    {
        $path = __DIR__.'/storage/distribution.txt';

        if(file_exists($path)) {
            $responsible_id = file_get_contents($path);

            $key_responsible_id = array_search($responsible_id, $this->config->responsible_users);

            $next_key_responsible_id = $key_responsible_id + 1;

            if($next_key_responsible_id > count($this->config->responsible_users))
                $next_responsible_id = $this->config->responsible_users[0];
            else
                $next_responsible_id = $this->config->responsible_users[$next_key_responsible_id];

            file_put_contents($path, $next_responsible_id);

            return $responsible_id;

        } else {
            file_put_contents($path, $this->config->responsible_users[0]);

            return $this->config->responsible_users[0];
        }
    }
}
