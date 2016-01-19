<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
date_default_timezone_set('America/Los_Angeles');
require_once ('includes/sffunctions.php');


//Data to use for Property Search
$PropertyStreetAddress = ($_GET['streetnum'] . ' ' . substr($_GET['streetname'], 0, strrpos($_GET['streetname'], ' ')) );   //Strip suffix
$Unit = ($_GET['unit']);
$City = ($_GET['city']);
$StateCode = $_GET['state'];
$ZipCode = $_GET['zip'];

$searchResult = SFSearchPropertyFastQuote(null, $PropertyStreetAddress, $Unit, $City, $StateCode, $ZipCode );

if( $searchResult != false){    //Property in SF Found
    
    $FQID = $searchResult['FQID'];
    $FQZIP = $searchResult['FQZIP'];
    
    setcookie("fastquoteid", $FQID, time() + 298500);
    setcookie("fastquotezip", $FQZIP, time() + 298500); //Fix for existing cookie override issue
    
    header("Location: ratequote.php?fastquoteid=$FQID&fastquotezip=$FQZIP");
    
}
else{    //No property found, execute RealtyTrac API pull
    //Your Login, Password, API, Key for Realty Trac
    $login = 'hmfinet';
    $password = '1hmfinet!';
    $apikey = '509089ed-8454-4013-b3d7-395c952a9fb2';
    
    //Data to send to the Realty Trac API
    $PropertyStreetNum = urlencode($_GET['streetnum']);
    $PropertyStreetName = urlencode($_GET['streetname']);
    $PropertyStreetAddress = urlencode($_GET['streetnum'] . ' ' . $_GET['streetname']);
    if( $_GET['unit'] != ''){
        $PropertyStreetAddress .= urlencode(' #'.$_GET['unit']);
    }
    $Unit = urlencode($_GET['unit']);
    $City = urlencode($_GET['city']);
    $StateCode = $_GET['state'];
    $ZipCode = $_GET['zip'];
    
    //Send the JSON request
    $jsonurl = 'http://dlpapi.realtytrac.com/Reports/Get?ApiKey='.$apikey.'&Login='.$login.'&Password='.$password.'&JobID=&LoanNumber=&PreparedBy=&ResellerID=&PreparedFor=&OwnerFirstName=&OwnerLastName=&AddressType=&PropertyStreetAddress='.$PropertyStreetAddress.'&AddressNumber='.$PropertyStreetNum.'&StartAddressNumberRange=&EndAddressNumberRange=&StreetDir=&StreetName='.$PropertyStreetName.'&StreetSuffix=&Unit='.$Unit.'&City='.$City.'&StateCode='.$StateCode.'&ZipCode='.$ZipCode.'&PropertyParcelID=&SAPropertyID=&APN=&ApnRangeStart=&ApnRangeEnd=&Latitude=&Longitude=&Radius=&SearchType=&NumberOfRecords=&Sort=&Format=JSON&ReportID=103%2c106&R103_SettingsMode=';
    $response = file_get_contents($jsonurl);
    
    //Decode the response
    $responseData = json_decode($response, true);
    $propertyArr = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"]; //Get property array. Simplify notation.
    
    //echo "<pre>";
    //print_r($responseData);
    //echo "</pre>";
    
    //Function to check uppercase and if not all uppercase, uppercase first letter of proper names
    function properCase($propername) {
        //if (ctype_upper($propername)) {
            $propername = ucwords(strtolower($propername));
        //}
        return $propername;
    }
    
    print_debug($propertyArr[0]);
    //Owner 1 response
    $labelname = $propertyArr[0]["_OWNER"][0]["@_Name"];
    $nameArray = explode(',', $labelname, 2);
    $lastname = properCase($nameArray[0]);
    $nameArray2 = explode(' ', $nameArray[1], 2);
    $firstname = properCase($nameArray2[0]);
    if(isset($nameArray2[1])) { $middlename = properCase($nameArray2[1]); }
    
    //Parsed names not available with Basic Profile report
    //$lastname = properCase($propertyArr[0]["_OWNER"][0]["_PARSED_NAME_ext"]["@_LastName"]);
    //$firstname = properCase($propertyArr[0]["_OWNER"][0]["_PARSED_NAME_ext"]["@_FirstName"]);
    //$middlename = properCase($propertyArr[0]["_OWNER"][0]["_PARSED_NAME_ext"]["@_MiddleName"]);
    if ($lastname == '') {  //If no lastname, make full name lastname so contact will be created in Salesforce i.e. for trusts.
        $lastname = properCase($labelname);
        $firstname = '';    //Eliminate duplication of first name and last name if no last name.
    }
    
    //Owner 2 response
    $labelname2 = properCase($propertyArr[0]["_OWNER"][1]["@_Name"]);
    $nameArray = explode(',', $labelname2, 2);
    $lastname2 = properCase($nameArray[0]);
    $nameArray2 = explode(' ', $nameArray[1], 2);
    $firstname2 = properCase($nameArray2[0]);
    if(isset($nameArray2[1])) { $middlename2 = properCase($nameArray2[1]); }
    
    //Property address information response
    $propertyStreetAddress = $propertyArr[0]["@_StreetAddress"];
    $propertycity = $propertyArr[0]["@_City"];
    $propertystate = $propertyArr[0]["@_State"];
    $propertyzipcode = $propertyArr[0]["@_PostalCode"];
    $county = properCase($propertyArr[0]["_IDENTIFICATION"]["@CountyFIPSName_ext"]);
    
    //Mortgage information response
    foreach ($propertyArr as $element) {    //Loop JSON decoded array
        $loanType = $element["SALES_HISTORY"]["LOAN_ext"][1]["@_Type"];
        $equityLine = $element["SALES_HISTORY"]["LOAN_ext"][1]["@_EquityLineOfCreditIndicator"];
        if ($loanType == 'First' and $equityLine == 0) {    //Check if loan is first and equity line is false
            
            //Get mortgage information response for first mortgage that is not an equity line
            $loanDescription = $element["SALES_HISTORY"]["LOAN_ext"][1]["@_AmortizationDescription_ext"];
            $loanamt = $element["SALES_HISTORY"]["LOAN_ext"][1]["@_Amount"];
            $loandate = $element["SALES_HISTORY"]["@TransferDate_ext"];
            $loangroup = $element["SALES_HISTORY"]["LOAN_ext"][1]["@MortgageType"];
            $lenderfirstname = $element["SALES_HISTORY"]["LOAN_ext"][1]["@LenderFirstName"];
            $lenderlastname = $element["SALES_HISTORY"]["LOAN_ext"][1]["@LenderLastName"];
            $lendername = $lenderfirstname.' '.$lenderlastname;
            
            //Fix accessing loan maturity dates in PHP beyond 2038
            $loandate = date('Y-m-d', strtotime($loandate));
            $maturitydate = new DateTime($loandate);
            $interval = new DateInterval('P30Y');   //Assume 30-year loan
            $maturitydate->add($interval);
            $maturitydate = $maturitydate->format('Y-m-d')."\n";
            
            //Stop loop at first instance of first mortgage that is not an equity line
            break; 
        }   
    }
    
    //Other property information
    $countPropertyArr = count($propertyArr) - 1;    //Count elements in $propertArr to find Basic Profile Report at the end
    $propertytype = $propertyArr[$countPropertyArr]["@StandardUseDescription_ext"];
    $saleprice = $propertyArr[$countPropertyArr]["SALES_HISTORY"]["@PropertySalesAmount"];
    $parcelid = $propertyArr[$countPropertyArr]["_IDENTIFICATION"]["@AssessorsParcelIdentifier"];
    $assessedvalue = $propertyArr[$countPropertyArr]["_TAX"]["@_TotalAssessedValueAmount"];
    $propertytaxamt = $propertyArr[$countPropertyArr]["_TAX"]["@_TotalTaxAmount"];
    $yearbuilt = $propertyArr[$countPropertyArr]["STRUCTURE"]["STRUCTURE_ANALYSIS"]["@PropertyStructureBuiltYear"];
    
    //Defaults for empty fields
    IF($loangroup ==''){
        $loangroup = 'CONV';
    }
    
    //Print
    
    echo 'FULL NAME: '.$labelname.'<br/>';
    echo 'LAST NAME: '.$lastname.'<br/>';
    echo 'FIRST NAME: '.$firstname.'<br/>';
    echo 'MIDDLE NAME: '.$middlename.'<br/>';
    echo 'FULL NAME 2:'.$labelname2.'<br/>';
    echo 'LAST NAME 2:'.$lastname2.'<br/>';
    echo 'FIRST NAME 2:'.$firstname2.'<br/>';
    echo 'MIDDLE NAME 2:'.$middlename2.'<br/>';
    echo 'STREET ADDRESS: '.$propertyStreetAddress.'<br/>';
    echo 'CITY: '.$propertycity.'<br/>';
    echo 'STATE: '.$propertystate.'<br/>';
    echo 'ZIP: '.$propertyzipcode.'<br/>';
    echo 'COUNTY: '.$county.'<br/>';
    echo 'LOAN TYPE: '.$loanDescription.'<br/>';
    echo 'LOAN AMOUNT: '.$loanamt.'<br/>';
    echo 'LOAN DATE: '.$loandate.'<br/>';
    echo 'LOAN GROUP: '.$loangroup.'<br/>';
    echo 'PROPERTY TYPE: '.$propertytype.'<br/>';
    echo 'LENDER FIRST NAME: '.$lenderfirstname.'<br/>';
    echo 'LENDER LAST NAME: '.$lenderlastname.'<br/>';
    echo 'LENDER NAME: '.$lendername.'<br/>';
    echo 'MATURITY DATE: '.$maturitydate.'<br/>';
    echo 'SALE PRICE: '.$saleprice.'<br/>';
    echo 'APN: '.$parcelid.'<br/>';
    echo 'PROPERTY TAX AMOUNT: '.$propertytaxamt.'<br/>';
    echo 'ASSESSED VALUE: '.$assessedvalue.'<br/>';
    echo 'YEAR BUILT: '.$yearbuilt.'<br/>';
    
    //Die before doing Salesforce stuff
    die();
    
    //Create Salesforce Account
    $sObject = new stdclass();
    $sObject->Name = $firstname . ' ' . $lastname;
    $sObject->Type = 'Prospect';
    $sObject->AccountSource = 'FastQuote';
    $sObject->Rating = 'Cold';
    $sObject->Lead_Status__c = 'No Status';
    IF( !empty($loanamt) ){
        $sObject->Loan_Amount__c = $loanamt;
    }
    $sObject->Interest_Rate__c = 0;
    $sObject->Estimated_Price__c = $saleprice;
    $sObject->Property_Tax_Amount__c = $propertytaxamt;
    $sObject->Type_of_Property__c = $propertytype;
    $sObject->Lender_Name__c = $lendername;
    IF ( !empty($loandate) ){
        $sObject->Loan_Start_Date__c = $loandate;
    }
    $sObject->Loan_Group__c = $loangroup;
    $sObject->Assessed_Value__c = $assessedvalue;
    $sObject->Year_Home_Built__c = $yearbuilt;
    $sObject->Loan_Maturity_Date__c = $maturitydate;
    $sObject->Parcel_Number__c = $parcelid;
    
    $sObject->County__c = $county;
    
    $sObject->BillingStreet = $propertyStreetAddress;
    $sObject->BillingCity = $propertycity;
    $sObject->BillingState = $propertystate;
    $sObject->BillingPostalCode = $propertyzipcode;
    
    $sObjectArray = array($sObject);
    //print_debug($sObjectArray);
    
    $acctResult = SFCreate(null, $sObjectArray, 'Account');
    //print_debug($acctResult);
    
    //Create Contacts
    if($acctResult['success'] == true){
        $acctID = $acctResult[0]->id;
        
        $sObject = new stdclass();
        $sObject->AccountId = $acctID;
        $sObject->FirstName = $firstname;
        $sObject->LastName = $lastname;
        $sObject->Primary__c = 'TRUE';
        $sObject->MailingStreet = $propertyStreetAddress;
        $sObject->MailingCity = $propertycity;
        $sObject->MailingState = $propertystate;
        $sObject->MailingPostalCode = $propertyzipcode;
        
        $sObjectArray = array($sObject);
        
        if( $lastname2 != ''){  //Add co-owner/co-borrower
            $sObject = new stdclass();
            $sObject->AccountId = $acctID;
            $sObject->FirstName = $firstname2;
            $sObject->LastName = $lastname2;
            $sObject->MailingStreet = $propertyStreetAddress;
            $sObject->MailingCity = $propertycity;
            $sObject->MailingState = $propertystate;
            $sObject->MailingPostalCode = $propertyzipcode;
            
            $sObjectArray[] = $sObject;
        }
        //print_debug($sObjectArray);
        $contactResult = SFCreate(null, $sObjectArray, 'Contact');
        
        $searchResult = SFSearchPropertyFastQuotebyID(null, $acctID);
        $FQID = $searchResult['FQID'];
        $FQZIP = $searchResult['FQZIP'];
        setcookie("fastquoteid", $FQID, time() + 298500);
        setcookie("fastquotezip", $FQZIP, time() + 298500); //Fix for existing cookie override issue
    
        header("Location: ratequote.php?fastquoteid=$FQID&fastquotezip=$FQZIP");
        
    }
    else{
        //Handle Account Write Failure
        echo "An error occurred.  Please try again later...";
        print_debug($acctResult);
    }
    
    
}
    
?>