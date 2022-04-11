<?
/*
// 실물 확인 접수 전송 실패
$sResponse = "";
*/
/*
// 실물 확인 접수 전송 성공
$sResponse = "<?xml version='1.0' encoding='utf-8'?><soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:n1=\"http://base.checkout.platform.nhncorp.com/\" xmlns:n=\"http://mall.checkout.platform.nhncorp.com/\"><soapenv:Body><n:ApproveCollectedExchangeResponse><n1:RequestID/><n1:ResponseType>SUCCESS</n1:ResponseType><n1:ResponseTime>368</n1:ResponseTime><n1:DetailLevel>Full</n1:DetailLevel><n1:Version>4.0</n1:Version><n1:Release>UNKNOWN</n1:Release><n1:Timestamp>2019-12-06T05:53:43.65Z</n1:Timestamp><n1:MessageID>bdy71IZZSeGyqjr_DvrSYw</n1:MessageID></n:ApproveCollectedExchangeResponse></soapenv:Body></soapenv:Envelope>";
*/

$xmlResponseList = $this->_g_xmlRespBody->getElementsByTagName('ApproveCollectedExchangeResponse')->item(0);

if(!$xmlResponseList && count($xmlResponseList)==0)
	$oRst = new BaseObject(-1, 'invalid ApproveCollectedExchangeResponse counts');
else
	$oRst = new BaseObject();
?>