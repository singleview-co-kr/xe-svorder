<?
/*
// 운송장 재등록 실패
$sResponse = "<?xml version='1.0' encoding='utf-8'?><soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:n1=\"http://base.checkout.platform.nhncorp.com/\" xmlns:n=\"http://mall.checkout.platform.nhncorp.com/\"><soapenv:Body><n:ShipProductOrderResponse><n1:RequestID/><n1:ResponseType>ERROR</n1:ResponseType><n1:ResponseTime>551</n1:ResponseTime><n1:Error><n1:Code>ERR-NC-UNKNOWN</n1:Code><n1:Message>주문상태 및 클레임상태를 확인하세요.</n1:Message><n1:Detail>Transaction ID: 45B8BEBA65ACF0FE379552840A6084EB</n1:Detail></n1:Error><n1:DetailLevel>Full</n1:DetailLevel><n1:Version>4.0</n1:Version><n1:Release>UNKNOWN</n1:Release><n1:Timestamp>2019-11-06T22:18:08.40Z</n1:Timestamp><n1:MessageID>GTF774768T4TV2ULEG24TV4M8C000001</n1:MessageID></n:ShipProductOrderResponse></soapenv:Body></soapenv:Envelope>";
*/
/*
// 운송장 등록 성공
$sResponse = "<?xml version='1.0' encoding='utf-8'?><soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:n1=\"http://base.checkout.platform.nhncorp.com/\" xmlns:n=\"http://mall.checkout.platform.nhncorp.com/\"><soapenv:Body><n:ShipProductOrderResponse><n1:RequestID/><n1:ResponseType>SUCCESS</n1:ResponseType><n1:ResponseTime>156</n1:ResponseTime><n1:DetailLevel>Full</n1:DetailLevel><n1:Version>4.0</n1:Version><n1:Release>UNKNOWN</n1:Release><n1:Timestamp>2019-11-06T08:49:21.26Z</n1:Timestamp><n1:MessageID>EV8MB7RL1P1RD3T7LM23KHB92800001F</n1:MessageID></n:ShipProductOrderResponse></soapenv:Body></soapenv:Envelope>";
*/
$xmlResponseList = $this->_g_xmlRespBody->getElementsByTagName('ShipProductOrderResponse')->item(0);
if(!$xmlResponseList && count($xmlResponseList)==0)
	$oRst = new BaseObject(-1, 'invalid ShipProductOrderResponse counts');
else
	$oRst = new BaseObject();
?>