<?php
$basePath=Yii::app()->basePath;
include_once ($basePath.'/helpers/admin/import_helper.php');
include_once ($basePath.'/helpers/common_helper.php');
class CopysurveyController extends CController
{
    /**
     * main action for creating survey, initiating participant table and adding participants
     */
    public  function actionIndex()
    {

        // global settings for APi connection
        $myJSONRPCClient = $this->globalSettings();

        // fetching the data from woocommerce
        $sku = $_GET['sku'];
        $survey_sku = $_GET['survey_sku'];
        $sku_array = explode('-',$sku);

        // check to the format having no 't' in it. starting from the surveyId
        if(!(strpos($sku, 't') !== false)){
            unset($sku_array[0]);
            $sku_array[1] = 't'.$sku_array[1];
        }
        $shiftArray = array_shift($sku_array);

        //number of tokens
        preg_match_all('!\d+!', $shiftArray, $token_numbers);
        $token_numbers = $token_numbers[0][0];
        if (!empty($sku_array)){
            $count = 1;
            $date = null;
            foreach ($sku_array as $sk){
                ($count != 1)? $comma = ', ': $comma = '';
                $d = strtotime('+'.$sk.' months');
                $date.= $comma.date("m/Y",$d);
                $count ++;
            }
        }
        $survey = Survey::model()->findByPk($survey_sku);
        $model = SurveyLanguageSetting::model()->findByPk([
            'surveyls_survey_id' => $survey_sku,
            'surveyls_language' => $survey->language,
        ]);
        $old_title = $model->surveyls_title;

        //customer details
        $customer_details = [
            'first_name' => $_GET['first_name'],
            'last_name' => $_GET['last_name'],
            'email' => $_GET['email'],
        ];


        // importing the survey template and creating the survey
        for ($i=0;$i<$_GET['quantity'];$i++) {
            $itemnumber = $i+1;
            $iteration_text = '';
            if (!empty($date)) {
                $iteration_text = ', Iterationen: '.$date;
            }
            //survey title
            if (empty($_GET['company'])){

                $survey_title = '['.$_GET['order_id'].'] '.$old_title.' - '. $_GET['first_name'].' '.$_GET['last_name'].
                    ', ' . date('d.m.Y') . ' (' . $token_numbers . ' Zugangsschlüssel'. $iteration_text .')';
            }
            else{
                $survey_title = '['.$_GET['order_id'].'] '.$old_title.' - '. $_GET['company'].
                    ', ' . date('d.m.Y') . ' (' . $token_numbers . ' Zugangsschlüssel'. $iteration_text .')';
            }

            $customer_details['title'] = $survey_title;


            //importing the survey from the survey template.
            $surveyId = $this->Import($this->sessionKey($myJSONRPCClient), $myJSONRPCClient, $survey_title, $survey_sku);

            // copying the old survey template.
            $new_survey = Survey::model()->findByPk($surveyId);
            $new_survey->template = $survey->template;
            $new_survey->active = 'Y';

            // initiating the token participants table for the given survey
            $activate_token = $myJSONRPCClient->activate_tokens($this->sessionKey($myJSONRPCClient), $surveyId);

            //adding the participants to the token table
            $token_array =  $this->addtokens($token_numbers, $sku_array, $surveyId, $this->sessionKey($myJSONRPCClient), $myJSONRPCClient);

            //activatye the survey
            $myJSONRPCClient->activate_survey($this->sessionKey($myJSONRPCClient), $surveyId);
            if (isset($new_survey)){
                $randomNumber = round(($surveyId * rand(100000,999999)));
                $new_survey->faxto = $randomNumber;
            }
            $new_survey->save();
            // send email to customer with token list
            $this->sendEmail($surveyId, $token_array, $customer_details, $new_survey->faxto);
        }
    }

    /**
     * @return \org\jsonrpcphp\JsonRPCClient
     * settings connection credentials for API
     */
    public function globalSettings()
    {
        // limesurvey remotecontrol API connection
        $basePath = Yii::app()->basePath;
        include_once ($basePath.'/controllers/org/jsonrpcphp/JsonRPCClient.php');

        define( 'LS_BASEURL', 'enter-the-url.de');  // adjust this one to your actual LimeSurvey URL
        define( 'LS_USER', 'username' );                      // limesurvey admin username
        define( 'LS_PASSWORD','password');              // limesurvey admin password

        // instantiate a new client
        $myJSONRPCClient = new \org\jsonrpcphp\JsonRPCClient( 'https://data.self-locator.de/admin/remotecontrol' );
        return $myJSONRPCClient;
    }

    /**
     * @param $myJSONRPCClient
     * @return mixed
     */
    public  function sessionKey($myJSONRPCClient)
    {
        $sessionKey = $myJSONRPCClient->get_session_key( LS_USER, LS_PASSWORD );
        return $sessionKey;
    }
    /**
     * @param $token_number
     * @param $iterations
     * @param $surveyId
     * @param $sessionKey
     * @param $myJSONRPCClient
     */
    public function addtokens($token_number, $iterations, $surveyId, $sessionKey, $myJSONRPCClient)
    {
        $participantData = [];
        $token_array = [];

        // settings  attribute_6 for first 50 tokens
        for ($i = 0 ; $i < $token_number ; $i++){
            $participantData[] = [
                'token' => '',
                'attribute_6' => 1,
            ];
        }
        $result = $myJSONRPCClient->add_participants($sessionKey, $surveyId, $participantData, true);
        if (isset($result)){
            foreach ($result as $tokenResult){
                $token_array[] = $tokenResult['token'];

                // Settings the parent token for first iteration of tokens.
                $pModel = Token::model($surveyId)->findByAttributes(
                    [
                        'token' => $tokenResult['token']
                    ]
                );
                if (isset($pModel->attribute_7)){
                    $pModel->attribute_7 = $tokenResult['token'];
                    $pModel->save();
                }
            }
        }

        $participantData = null;
        if (!empty($iterations)) {
            foreach ($iterations as $it) {
                if (!in_array($it, [0,1])) {
                    $count = 0;
                    $d = strtotime('+' . $it . ' months');
                    for ($i=0;$i<$token_number;$i++) {
                        $participantData[] = [
                            'token' => '',
                            'validfrom' => date("Y-m-d h:i:s", $d),
                            'attribute_6' => $it,
                            'attribute_7' => $token_array[$count],
                        ];
                        $count++;
                    }
                }
            }
        }
        $result = $myJSONRPCClient->add_participants($sessionKey, $surveyId, $participantData, true);

        // return the token array just created
        return $token_array;
    }

    /**
     * @param $surveyId
     * @param $token_array
     * @param $customer_details
     * @param $faxto
     */
    public function sendEmail($surveyId,$token_array,$customer_details,$faxto)
    {
        //  $surveyInfo = getSurveyInfo($surveyId);
        $count = 1;
        $token = '<table>';
        foreach ($token_array as $tk){
            $token .= '<tr><td> '.$tk.' </td></tr>';
            $count ++;
        }


        $token .= '</tr></table>';
        $subject = 'Ihre Zugangsschlüssel für Umfrage';
        $body = '<br>
            Liebe/r '.$customer_details["first_name"].' '.$customer_details["last_name"].'
            <br>
            <br>
            vielen Dank für Ihre Bestellung. Wir haben für Sie die Umfrage "<b>'.$customer_details["title"].'</b>"
            mit ID '.$surveyId.' erstellt. 
            <br>
            <br>
            Der Link zu Ihrer Umfrage lautet:
            <br>
            <a href="https://data.self-locator.de/index.php/'.$surveyId.'">
            https://data.self-locator.de/index.php/'.$surveyId.'
            </a>
            <br>
            <br>
            Der Link zu Ihrem Report lautet:
            <br>
            <a href="https://data.self-locator.de/index.php/dashboard/module/redirect/sid/'.$surveyId.'/k/'.$faxto.'">
            https://data.self-locator.de/index.php/dashboard/module/redirect/sid/'.$surveyId.'/k/'.$faxto.'
            </a>
            <br>
            <br>
            Am Ende dieser Email finden Sie eine Liste mit Zugriffsschlüsseln, die einmalig das Ausfüllen der Umfrage erlauben.
            Jeder Mitarbeiter kann am Ende der Umfrage die Details zu seinen/ihren Feedbackgebern eintragen. Diese werden 
            anschließend vom System automatisch eingeladen.
            <br>
            Bei Fragen stehen wir Ihnen gerne jederzeit zur Verfügung.
            <br>
            <br>
            Mit freundlichen Grüßen
            <br>
            Team Self-Locator
            <br>
            <br>
            Ihre Zugangsschlüssel:
            <br>
            '.$token;
        // Always set content-type when sending HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

        // More headers
        //  $headers .= 'From: <fawad.abbasi@survey-consulting.com>' . "\r\n";

        $to = html_entity_decode( $customer_details['email']);

        $from = "team@self-locator.de";
        SendEmailMessage(

            $body,
            $subject,
            $to,
            $from,
            Yii::app()->getConfig('sitename'),
            true,
            null,
            null,
            $headers
        );
    }
    /*
     * Import the content from a survey template and create a new survey...
     * @param String $sessionKey
     * @param object $myJSONRPCClient
     */
    public function Import($sessionKey,$myJSONRPCClient,$survey_title,$survey_sku)
    {
        $survey_id = $myJSONRPCClient->copy_survey($sessionKey,$survey_sku,$survey_title);

        // release the session key
        $myJSONRPCClient->release_session_key( $sessionKey );

        //return surveyId
        return $survey_id['newsid'];
    }
    /**
     * @param $sessionKey
     * @param $surveyId
     * @param $myJSONRPCClient
     */
    public function createTokens($sessionKey,$surveyId,$myJSONRPCClient)
    {
        $result = $myJSONRPCClient->activate_tokens($sessionKey,$surveyId,array());
    }

    /**
     * @param $sessionKey
     * @param $surveyId
     * @param $myJSONRPCClient
     */
    public function activateSurvey($sessionKey,$surveyId,$myJSONRPCClient)
    {
        $result = $myJSONRPCClient->activate_survey($sessionKey,$surveyId);
    }

    /**
     * @throws CHttpException
     */
    Public function actionChecksurvey()
    {
        $surveyId = $_GET['sid'];
        $survey = Survey::model()->findByPk($surveyId);

        if (isset($survey)) {
            $token = $_GET['token'];
            $tokenModel = Token::model($surveyId)->findByPk($token);

            if (isset($tokenModel)){
                $this->redirect('/index.php/'.$surveyId.'?token='.$token);
            }
        }
        $this->redirect('/index.php/'.$surveyId.'?token='.$token.'&newtest=Y');
        echo 'Survey or token not valid';
        // CHttpException(401.1,'Survey or token not valid');


    }
}

