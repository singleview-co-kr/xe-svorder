<?
/*
$sResponse="<?xml version='1.0' encoding='utf-8'?><soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:n1=\"http://base.checkout.platform.nhncorp.com/\" xmlns:n=\"http://mall.checkout.platform.nhncorp.com/\"><soapenv:Body><n:DelayProductOrderResponse><n1:RequestID/><n1:ResponseType>ERROR</n1:ResponseType><n1:ResponseTime>61</n1:ResponseTime><n1:Error><n1:Code>ERR-NC-UNKNOWN</n1:Code><n1:Message>이미 발송지연 안내 처리가 된 주문입니다.</n1:Message><n1:Detail>Transaction ID: F2FB15C3A344F59D5513374840A62F0F9</n1:Detail></n1:Error><n1:DetailLevel>Full</n1:DetailLevel><n1:Version>4.0</n1:Version><n1:Release>UNKNOWN</n1:Release><n1:Timestamp>2019-11-28T03:53:07.58Z</n1:Timestamp><n1:MessageID>J2B1P01BQH3HVCSA3FAIM9HQCG00001J</n1:MessageID></n:DelayProductOrderResponse></soapenv:Body></soapenv:Envelope>";
*/
/*
$sResponse="<?xml version='1.0' encoding='utf-8'?><soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:n1=\"http://base.checkout.platform.nhncorp.com/\" xmlns:n=\"http://mall.checkout.platform.nhncorp.com/\"><soapenv:Body><n:DelayProductOrderResponse><n1:RequestID/><n1:ResponseType>SUCCESS</n1:ResponseType><n1:ResponseTime>225</n1:ResponseTime><n1:DetailLevel>Full</n1:DetailLevel><n1:Version>4.0</n1:Version><n1:Release>UNKNOWN</n1:Release><n1:Timestamp>2019-11-28T03:48:57.40Z</n1:Timestamp><n1:MessageID>7HO7D3UNS56971R7PIM5QM3C2O00001I</n1:MessageID></n:DelayProductOrderResponse></soapenv:Body></soapenv:Envelope>";
*/

$xmlResponseList = $this->_g_xmlRespBody->getElementsByTagName('DelayProductOrderResponse')->item(0);

if(!$xmlResponseList && count($xmlResponseList)==0)
	$oRst = new BaseObject(-1, 'invalid DelayProductOrderResponse counts');
else
	$oRst = new BaseObject();
?>