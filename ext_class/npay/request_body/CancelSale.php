<?
//PRODUCT_UNSATISFIED 서비스 및 상품 불만족 - 판매 취소 시 사용 가능
//DELAYED_DELIVERY 배송 지연 - 판매 취소 시 사용 가능
//SOLD_OUT 상품 품절  - 판매 취소 시 사용 가능
//INTENT_CHANGED 구매 의사 취소 - 반품 접수 시 사용 가능
//COLOR_AND_SIZE 색상 및 사이즈 - 변경 반품 접수 시 사용 가능
//WRONG_ORDER 다른 상품 잘못 주문 - 반품 접수 시 사용 가능
//PRODUCT_UNSATISFIED 서비스 및 상품 불만족 - 반품 접수 시 사용 가능
//DELAYED_DELIVERY 배송 지연 - 반품 접수 시 사용 가능
//SOLD_OUT 상품 품절 - 반품 접수 시 사용 가능
//DROPPED_DELIVERY 배송 누락 - 반품 접수 시 사용 가능
//BROKEN 상품 파손 - 반품 접수 시 사용 가능
//INCORRECT_INFO 상품 정보 상이  - 반품 접수 시 사용 가능
//WRONG_DELIVERY 오배송 - 반품 접수 시 사용 가능
//WRONG_OPTION 색상 등이 다른 상품을 잘못 배송  - 반품 접수 시 사용 가능
//ETC 기타 - API에서 지정 불가
//NOT_YET_DISCUSSION 상호 협의가 완료되지 않은 주문 건 - 개인 간 에스크로 거래에서 사용 가능
//OUT_OF_STOCK 재고 부족으로 인한 판매 불가 - 개인 간 에스크로 거래에서 사용 가능
//SALE_INTENT_CHANGED 판매 의사 변심으로 인한 거부 - 개인 간 에스크로 거래에서 사용 가능
//NOT_YET_PAYMENT 구매자의 미결제로 인한 거부 - 개인 간 에스크로 거래에서 사용 가능
$request_body="<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:mall=\"http://mall.checkout.platform.nhncorp.com/\" xmlns:base=\"http://base.checkout.platform.nhncorp.com/\">
   <soapenv:Header/>
   <soapenv:Body>
      <mall:CancelSaleRequest>
         <base:AccessCredentials>
            <base:AccessLicense>".$accessLicense."</base:AccessLicense>
            <base:Timestamp>".$timestamp."</base:Timestamp>
            <base:Signature>".$signature."</base:Signature>
         </base:AccessCredentials>
         <base:RequestID></base:RequestID>
         <base:DetailLevel>".$detailLevel."</base:DetailLevel>
         <base:Version>".$version."</base:Version>
         <mall:ProductOrderID>".$sProductOrderID."</mall:ProductOrderID>
         <mall:CancelReasonCode>".$sCancelReasonCode."</mall:CancelReasonCode>
      </mall:CancelSaleRequest>
   </soapenv:Body>
</soapenv:Envelope>";
?>