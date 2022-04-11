<?
//GENERAL 텍스트 리뷰(일반, 한 달 사용) PREMIUM 포토/동영상 리뷰(일반, 한 달 사용)
$request_body="<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:mall=\"http://mall.checkout.platform.nhncorp.com/\" xmlns:base=\"http://base.checkout.platform.nhncorp.com/\">
   <soapenv:Header/>
   <soapenv:Body>
      <mall:GetPurchaseReviewListRequest>
         <base:AccessCredentials>
            <base:AccessLicense>".$accessLicense."</base:AccessLicense>
            <base:Timestamp>".$timestamp."</base:Timestamp>
            <base:Signature>".$signature."</base:Signature>
         </base:AccessCredentials>
         <base:RequestID></base:RequestID>
         <base:DetailLevel>".$detailLevel."</base:DetailLevel>
         <base:Version>".$version."</base:Version>
         <base:InquiryTimeFrom>".$sInquiryTimeFrom."</base:InquiryTimeFrom>
         <base:InquiryTimeTo>".$sInquiryTimeTo."</base:InquiryTimeTo>
         <base:InquiryExtraData></base:InquiryExtraData>
         <mall:MallID>".$sMallId."</mall:MallID>
         <mall:PurchaseReviewClassType>".$sReviewClass."</mall:PurchaseReviewClassType> 
      </mall:GetPurchaseReviewListRequest>
   </soapenv:Body>
</soapenv:Envelope>";
?>