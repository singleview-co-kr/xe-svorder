jQuery(function($){
	var option = {
		changeMonth: true,
		changeYear: true,
		gotoCurrent: false,
		yearRange:'-100:+10',
		dateFormat:'yy-mm-dd',
		onSelect:function() {
			$(this).prev('input[type="hidden"]').val(this.value.replace(/-/g,""))}
		};
	$.extend(option,$.datepicker.regional['{$lang_type}']);
	$(".inputDate").datepicker(option);
	$(".dateRemover").click(function() {
		$(this).parent().prevAll('input').val('');
		return false;
	});
	//$('.escrow').escrow();

	jQuery('.receipt').click(function() {
		var order_srl = jQuery(this).attr('data-order-srl');
		var $_parent = jQuery(this).parent();
		exec_xml(
			'svpg',
			'getSvpgReceipt',
			{order_srl:order_srl},
			function(ret){
				var tpl = ret.tpl.replace(/<enter>/g, '\n');
				$_parent.html(tpl);
			},
			['error','message','tpl']
		);

	});
});

function updateOrderStatus( nOrderSrl )
{
	if( typeof nOrderSrl == 'undefined' )
	{
		alert( '잘못된 주문번호입니다.' );
		return;
	}

	if( nOrderSrl.length == 0 )
	{
		alert( '잘못된 주문번호입니다.' );
		return;
	}
	
	var sOriginalOrderStatus = jQuery('#target_order_status option:eq(0)').val();
	// select box ID로 접근하여 선택된 값 읽기
	var sTargetOrderStatus = jQuery('#target_order_status option:selected').val();
	
	if( sOriginalOrderStatus == sTargetOrderStatus )
	{
		alert( '변경할 상태가 원래 상태와 동일합니다.' );
		return;
	}

	var sUpdateReason = prompt("주문 상태 변경 사유를 입력해 주세요.", "예)주문 취소");
	if( sUpdateReason == null )
		return;
	else if( sUpdateReason == "예)주문 취소" || sUpdateReason == '' || sUpdateReason == null )
	{
		alert( '주문 상태 변경 사유를 입력해 주세요.' );
		return;
	}

	var params = new Array();
	params['order_srl'] = nOrderSrl;
	params['update_reason'] = sUpdateReason;
	params['target_order_status'] = sTargetOrderStatus;
	exec_xml('svorder', 'procSvorderUpdateOrderStatus', params, function(ret_obj){
		alert(ret_obj['message']);
// Google Analytics Code Begin (3/4/2016 9:48 AM) singleview.co.kr --->
// 목표 상태가 결제 취소면 gatk 환불 코드 실행
//		gatkMypage.refund( nOrderSrl );
//		gatkHeader.close();
// Google Analytics Code End (3/4/2016 9:48 AM) singleview.co.kr --->
		location.reload();
	});
}

/*
function cancelSettlement( nOrderSrl )
{
	if( typeof nOrderSrl == 'undefined' )
	{
		alert( '잘못된 주문번호입니다.' );
		return;
	}

	if( nOrderSrl.length == 0 )
	{
		alert( '잘못된 주문번호입니다.' );
		return;
	}

	var sCancelReason = prompt("결제 취소 사유를 입력해 주세요.", "예)주문 취소");
	if( sCancelReason == null )
		return;
	else if( sCancelReason == "예)주문 취소" || sCancelReason == '' || sCancelReason == null )
	{
		alert( '취소사유를 입력해 주세요.' );
		return;
	}

	var params = new Array();
	params['order_srl'] = nOrderSrl;
	params['cancel_reason'] = sCancelReason;
	exec_xml('svorder', 'procSvorderCancelSettlement', params, function(ret_obj){
		alert(ret_obj['message']);
// Google Analytics Code Begin (3/4/2016 9:48 AM) singleview.co.kr --->
		gatkMypage.refund( nOrderSrl );
		gatkHeader.close();
// Google Analytics Code End (3/4/2016 9:48 AM) singleview.co.kr --->
		location.reload();
	});
}
*/

function number_format(nStr)
{
    nStr += '';
    x = nStr.split('.');
    x1 = x[0];
    x2 = x.length > 1 ? '.' + x[1] : '';
    var rgx = /(\d+)(\d{3})/;
    while (rgx.test(x1)) {
        x1 = x1.replace(rgx, '$1' + ',' + '$2');
    }
    return x1 + x2;
}

function change_period(days, month) {
	var currdate = new Date();
	if (days) {
		currdate = _addDays(currdate, -1 * days);
	}
	if (month) {
		currdate = _addMonth(currdate, -1 * month);
	}
	var startdate = jQuery.datepicker.formatDate('yymmdd', currdate);
	var startdateStr = jQuery.datepicker.formatDate('yy-mm-dd', currdate);
	jQuery('#orderlist .period input[name=startdate]').val(startdate);
	jQuery('#orderlist .period #startdateInput').val(startdateStr);
	jQuery('#fo_search').submit();
}

function _addDays(myDate, days) 
{
	return new Date(myDate.getTime() + days*24*60*60*1000);
}

function _addMonth(currDate, month) 
{
	var currDay   = currDate.getDate();
	var currMonth = currDate.getMonth();
	var currYear  = currDate.getFullYear();
	var ModMonth = currMonth + month;
	if (ModMonth > 12) 
	{
		ModMonth = ModMonth - 12;
		currYear = currYear + 1;
	}
	if (ModMonth < 0) 
	{
		ModMonth = 12 + (ModMonth);
		currYear = currYear - 1;
	}
	return new Date(currYear, ModMonth, currDay);
}