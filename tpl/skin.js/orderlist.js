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