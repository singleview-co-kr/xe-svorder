<?
// 서비스 타입 코드: CHECKOUT => 네이버페이 가맹점, SHOPN => 스토어팜 판매자
$request_body="<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:cus=\"http://customerinquiry.checkout.platform.nhncorp.com/\">
   <soapenv:Header/>
   <soapenv:Body>
      <cus:GetCustomerInquiryListRequest>
         <cus:RequestID></cus:RequestID>
         <cus:AccessCredentials>
            <cus:AccessLicense>".$accessLicense."</cus:AccessLicense>
            <cus:Timestamp>".$timestamp."</cus:Timestamp>
            <cus:Signature>".$signature."</cus:Signature>
         </cus:AccessCredentials>
         <cus:DetailLevel>".$detailLevel."</cus:DetailLevel>
         <cus:Version>".$version."</cus:Version>
         <ServiceType>".$sServiceType."</ServiceType>
         <MallID>".$sMallId."</MallID>
         <InquiryTimeFrom>".$sInquiryTimeFrom."</InquiryTimeFrom>
         <InquiryTimeTo>".$sInquiryTimeTo."</InquiryTimeTo>
         <IsAnswered>".$bAnswered."</IsAnswered>
      </cus:GetCustomerInquiryListRequest>
   </soapenv:Body>
</soapenv:Envelope>";
?>