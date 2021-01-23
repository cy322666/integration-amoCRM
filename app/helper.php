<?php

class helper extends \app
{
    public static function switchForm($form)
    {
        switch ($form) {
            case '1':
                return 111;
            case '2':
                return 222;
            default:
                return 333;
        }
    }

    public static function createTextNote($custom_fields = null)
    {
        $note = [];

        return $note;
    }
}