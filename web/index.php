<?php

/**
 *
 * Twilio twimlet for forwarding inbound calls
 * to the on-call engineer as defined in PagerDuty
 *
 * Designed to be hosted on Heroku
 *
 * (c) 2014 Vend Ltd.
 *
 */

require __DIR__ . '/../vendor/autoload.php';

// Set these Heroku config variables
$scheduleID = getenv('PAGERDUTY_SCHEDULE_ID');
$fallbackScheduleID = getenv('PAGERDUTY_FALLBACK_SCHEDULE_ID');
$APItoken   = getenv('PAGERDUTY_API_TOKEN');
$domain     = getenv('PAGERDUTY_DOMAIN');
$callerID   = getenv('CALLERID');

// Should we announce the local time of the on-call person?
// (helps raise awareness you might be getting somebody out of bed)
$announceTime = getenv('PHONEDUTY_ANNOUNCE_TIME');

$dialCallStatus = $_GET['DialCallStatus'];
if ($fallbackScheduleID != null && $dialCallStatus != null && $dialCallStatus != 'completed') {
    $scheduleID = $fallbackScheduleID;
}

$pagerduty = new \Vend\Phoneduty\Pagerduty($APItoken, $domain);

$userID = $pagerduty->getOncallUserForSchedule($scheduleID);

if (null !== $userID) {
    $user = $pagerduty->getUserDetails($userID);

    $attributes = array(
        'voice' => 'alice',
        'language' => 'fr-FR'
    );

    $time = "";
    if ($announceTime && $user['local_time']) {
        $time = sprintf("The current time in their timezone is %s.", $user['local_time']->format('g:ia'));
    }

    $twilioResponse = new Services_Twilio_Twiml();
    $response = sprintf("Veuillez patienter, nous vous mettons en relation avec %s. %s",
        $user['first_name'],
        $time
        );

    $dialParameters = array();
    if ($callerID) {
        $dialParameters['callerId'] = $callerID;
    }
    if ($scheduleID != $fallbackScheduleID) {
        $dialParameters['timeout'] = 20; // Avoid voicemail
        $dialParameters['action'] = '/';
        $dialParameters['method'] = 'GET';
    }

    $twilioResponse->say($response, $attributes);
    $twilioResponse->dial($user['phone_number'], $dialParameters);

    // send response
    if (!headers_sent()) {
        header('Content-type: text/xml');
    }

    echo $twilioResponse;
}
