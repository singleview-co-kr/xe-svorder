<?
// 서비스 타입 코드
// CHECKOUT 네이버페이 가맹점
// SHOPN 스토어팜 판매자

// 명령어 타입 코드
// INSERT 답변 등록
// UPDATE 답변 수정
$request_body="<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:mall=\"http://mall.checkout.platform.nhncorp.com/\" xmlns:base=\"http://base.checkout.platform.nhncorp.com/\">
   <soapenv:Header/>
   <soapenv:Body>
<mall:AnswerCustomerInquiryRequest>
	<base:AccessCredentials>
		<base:AccessLicense>".$accessLicense."</base:AccessLicense>
		<base:Timestamp>".$timestamp."</base:Timestamp>
		<base:Signature>".$signature."</base:Signature>
	</base:AccessCredentials>
	<base:RequestID></base:RequestID>
	<base:DetailLevel>".$detailLevel."</base:DetailLevel>
	<base:Version>".$version."</base:Version>
	<mall:MallID>".$sMallId."</MallID>
	<mall:ServiceType>".$sServiceType."</mall:ServiceType>
	<mall:InquiryID>".$sInquiryID."</mall:InquiryID>
	<mall:AnswerContent>".$sAnswerContent."</mall:AnswerContent>
	<mall:AnswerContentID>".$sAnswerContentID."</mall:AnswerContentID>
	<mall:ActionType>".$sActionType."</mall:ActionType>
	<mall:AnswerTempleteID>".$sAnswerTempleteID."</mall:AnswerTempleteID>
</mall:AnswerCustomerInquiryRequest>";
?>