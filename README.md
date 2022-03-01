# CopysurveyController
- The CopysurveyController impliments the LimeSurvey RemoteControl API.
LimeSurvey RemoteControl 2 is a XML-RPC/JSON-RPC based web service available in LimeSurvey
2.0 or more.

## Installation

- Copy the plugin file in the `application/controllers` folder of your limesurvey installation.
- To enable LSRC2 login to the LimeSurvey administration, go to Global settings,
  choose the tab 'Interfaces' and select  JSON-RPC.
- The permission set of the used username and password is the same as the  login of 
  administration with  user/password
- Download the jsonrpcphp [https://github.com/weberhofer/jsonrpcphp](https://github.com/weberhofer/jsonrpcphp) 
- sample link and array structure to call the controller
  
  [
  
    [product_id] => 544
    
    [variation_id] => 547
    
    [quantity] => 1
    
    [total] => 210.084034
    
    [name] => Umfrage Konfliktverhalten - 50
    
    [sku] => t50-1-1
    
    [first_name] => firstName
    
    [last_name] => lastName
    
    [email] => email@gmail.com
    
    [company] => LimeSurvey
    
    [survey_sku] => 719816
    
    [order_id] => 1020
  
  ]`
  
`$url = add_query_arg( $details,'https://testdata.self-locator.de/index.php/copysurvey/index');`
- Other API functions `http://limesurveyurl/index.php/admin/remotecontrol`

## Functions
- Start a predefined survey (change titles and things).
- Activate the survey.
- Add participant data/tokens when you need them.
- Send email to survey admin with the token. 
