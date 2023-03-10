<?php

namespace RoshaniSTPL\utility;

use Illuminate\Support\Facades\Mail;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Illuminate\Support\Facades\Log;

class ExceptionHelper {
    /*
     * This function change the timezone.
     *
     * */

    static public function ExceptionNotification($e, $controller, $subject, $request = NULL) {
        $errorArr['user_id'] = "";
        if(isset($request) && !empty($request)){
            if(isset($request->userId) && !empty($request->userId)){
                $errorArr['user_id'] = $request->userId;
            }
        }
        $error = $e->getMessage();
        $error .= ", Exception generated on line number : " . $e->getLine();
        $errorArr['Controller'] = $controller;
        $errorArr['error'] = $error;
        $to = env('MAIL_ID', 'tirthraj@savitriya.com');
        $arr = array_unique(explode(',', $to));
        $mailId = array();
        foreach ($arr as $val) {
            array_push($mailId, trim($val));
        }
        $errorArr['subject'] = $subject;

        if (env('SEND_EXCEPTION_MAIL', true)) {
            Mail::send('emails.welcome', ['user_id' => $errorArr['user_id'], 'exception' => $errorArr['error'], 'controller_exception' => $errorArr['Controller']], function ($message) use($mailId, $errorArr) {
                $message->to($mailId)->subject($errorArr['subject']);
                if (env('MAIL_RETURN_PATH', '') != "") {
                    $message->returnPath(env('MAIL_RETURN_PATH', ''));
                }
                $swiftMessage = $message->getSwiftMessage();
                $headers = $swiftMessage->getHeaders();
                $headers->addTextHeader('x-mail-origin-vvg', env('APP_URL', 'https://vvgdm.vtc.systems/'));
            });
            if (env('APP_ENV') === "staging") {
                Log::error($errorArr);
            } else if (env('APP_ENV') === "production" || env('APP_ENV') === "live") {
                // Bugsnag::notifyException($e);
            }
        }

        return true;
    }

    public static function stringHTMLEntities($string) {
        return htmlentities($string);
    }

    public static function stringHTMLEntitiesDecode($string) {
        return html_entity_decode($string);
    }

}
