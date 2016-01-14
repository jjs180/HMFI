<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);    
require_once ('includes/sffunctions.php');

//Data to use for Property Search
$PropertyStreetAddress = ($_GET['streetnum'] . ' ' . substr($_GET['streetname'], 0, strrpos($_GET['streetname'], ' ')) ); //Strip suffix
$Unit = ($_GET['unit']);
$City = ($_GET['city']);
$StateCode = $_GET['state'];
$ZipCode = $_GET['zip'];

$searchResult = SFSearchPropertyFastQuote(null, $PropertyStreetAddress, $Unit, $City, $StateCode, $ZipCode );

if( $searchResult != false){ //Property in SF Found
    
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
    $jsonurl = 'http://dlpapi.realtytrac.com/Reports/Get?ApiKey='.$apikey.'&Login='.$login.'&Password='.$password.'&JobID=&LoanNumber=&PreparedBy=&ResellerID=&PreparedFor=&OwnerFirstName=&OwnerLastName=&AddressType=&PropertyStreetAddress='.$PropertyStreetAddress.'&AddressNumber='.$PropertyStreetNum.'&StartAddressNumberRange=&EndAddressNumberRange=&StreetDir=&StreetName='.$PropertyStreetName.'&StreetSuffix=&Unit='.$Unit.'&City='.$City.'&StateCode='.$StateCode.'&ZipCode='.$ZipCode.'&PropertyParcelID=&SAPropertyID=&APN=&ApnRangeStart=&ApnRangeEnd=&Latitude=&Longitude=&Radius=&SearchType=&NumberOfRecords=&Sort=&Format=JSON&ReportID=102';
    //echo $jsonurl;
    $response = file_get_contents($jsonurl);
    
    //Decode the response
    $responseData = json_decode($response, true);
    //echo "<pre>";
    //print_r($responseData);
    //echo "</pre>";
    
    //Owner 1 response
    $labelname = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["_OWNER"][0]["@_Name"];
    $lastname = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["_OWNER"][0]["_PARSED_NAME_ext"]["@_LastName"];
    $firstname = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["_OWNER"][0]["_PARSED_NAME_ext"]["@_FirstName"];
    $middlename = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["_OWNER"][0]["_PARSED_NAME_ext"]["@_MiddleName"];
    
    //Owner 2 response
    $labelname2 = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["_OWNER"][1]["@_Name"];
    $lastname2 = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["_OWNER"][1]["_PARSED_NAME_ext"]["@_LastName"];
    $firstname2 = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["_OWNER"][1]["_PARSED_NAME_ext"]["@_FirstName"];
    $middlename2 = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["_OWNER"][1]["_PARSED_NAME_ext"]["@_MiddleName"];
    
    //Property address information response    
    $propertyhousenum = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["PARSED_STREET_ADDRESS"]["@_HouseNumber"];
    $propertystreetname = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["PARSED_STREET_ADDRESS"]["@_StreetName"];
    $propertystreetnamesuffix = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["PARSED_STREET_ADDRESS"]["@_StreetSuffix"];
    $propertyunitnum = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["PARSED_STREET_ADDRESS"]["@_ApartmentOrUnit"];
    $propertycity = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["@_City"];
    $propertystate = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["@_State"];
    $propertyzipcode = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["@_PostalCode"];
    $propertyzip4 = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["PARSED_STREET_ADDRESS"]["@PlusFourPostalCode"];
    $propertyzipzip4 = $propertyzipcode.'-'.$propertyzip4;
    $county = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["_IDENTIFICATION"]["@CountyFIPSName_ext"];
    
    //Mortgage information response
    $loantype = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["SALES_HISTORY"]["LOAN_ext"][0]["@_AmortizationDescription_ext"];
    $loanamt = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["SALES_HISTORY"]["LOAN_ext"][0]["@_Amount"];
    $loandate = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["SALES_HISTORY"]["@PropertySalesDate"]; //Note loan date is assumed to be sales date
    $loangroup = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["SALES_HISTORY"]["LOAN_ext"][0]["@MortgageType"];
    $propertytype = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["@StandardUseDescription_ext"];
    $lenderfirstname = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["SALES_HISTORY"]["LOAN_ext"][0]["@LenderFirstName"];
    $lenderlastname = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["SALES_HISTORY"]["LOAN_ext"][0]["@LenderLastName"];
    $lendername = $lenderfirstname.' '.$lenderlastname;
    $maturitydate = date('Y-m-d', strtotime($loandate . ' +30 years'));
    
    //Other property information
    $saleprice = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["SALES_HISTORY"]["@PropertySalesAmount"];
    $parcelid = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["_IDENTIFICATION"]["@AssessorsParcelIdentifier"];
    $assessedvalue = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["_TAX"]["@_TotalAssessedValueAmount"];
    $propertytaxamt = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["_TAX"]["@_TotalTaxAmount"];
    $yearbuilt = $responseData["RESPONSE_GROUP"]["RESPONSE"]["RESPONSE_DATA"]["PROPERTY_INFORMATION_RESPONSE_ext"]["SUBJECT_PROPERTY_ext"]["PROPERTY"][0]["STRUCTURE"]["STRUCTURE_ANALYSIS"]["@PropertyStructureBuiltYear"];
    
    //Defaults for empty fields
    IF($loangroup ==''){
        $loangroup = 'CONV';
    }
    
    //Print
    /*
    echo 'FULL NAME: '.$labelname.'<br/>';
    echo 'LAST NAME: '.$lastname.'<br/>';
    echo 'FIRST NAME: '.$firstname.'<br/>';
    echo 'MIDDLE NAME: '.$middlename.'<br/>';
    echo 'HOUSE NUMBER: '.$propertyhousenum.'<br/>';
    echo 'STREET NAME: '.$propertystreetname.'<br/>';
    echo 'UNIT: '.$propertyunitnum.'<br/>';
    echo 'STREET NAME SUFFIX: '.$propertystreetnamesuffix.'<br/>';
    echo 'CITY: '.$propertycity.'<br/>';
    echo 'STATE: '.$propertystate.'<br/>';
    echo 'ZIP: '.$propertyzipzip4.'<br/>';
    echo 'COUNTY: '.$county.'<br/>';
    echo 'LOAN TYPE: '.$loantype.'<br/>';
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
    */
    
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
    
    $propertyStreetAddress = $propertyhousenum . ' ' . $propertystreetname . ' ' . $propertystreetnamesuffix;
    if($propertyunitnum != ''){
        $propertyStreetAddress .=  ' #' .  $propertyunitnum;
    }
    $sObject->BillingStreet = $propertyStreetAddress;
    $sObject->BillingCity = $propertycity;
    $sObject->BillingState = $propertystate;
    $sObject->BillingPostalCode = $propertyzipcode;
    $sObject->Zip_Plus_4__c = $propertyzip4;
    
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
        $sObject->Zip_4__c = $propertyzip4;
        
        $sObjectArray = array($sObject);
        
        if( $lastname2 != ''){ //Add co-owner/co-borrower
            $sObject = new stdclass();
            $sObject->AccountId = $acctID;
            $sObject->FirstName = $firstname2;
            $sObject->LastName = $lastname2;
            $sObject->MailingStreet = $propertyStreetAddress;
            $sObject->MailingCity = $propertycity;
            $sObject->MailingState = $propertystate;
            $sObject->MailingPostalCode = $propertyzipcode;
            $sObject->Zip_4__c = $propertyzip4;
            
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