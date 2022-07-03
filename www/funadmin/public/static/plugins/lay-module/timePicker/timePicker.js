layui.define(["laydate","jquery"],function(exports){"use strict";var MOD_NAME="timePicker",$=layui.jquery,laydate=layui.laydate;var timePicker=function(){this.v="0.0.3"};timePicker.prototype.render=function(opt){var elem=$(opt.elem);var timeStamp=opt.options.timeStamp||false;var format=opt.options.format||"YYYY-MM-DD HH:mm:ss";elem.on("click",function(e){e.stopPropagation();if($(".timePicker").length>=1){$(".timePicker").remove();return false}var t=elem.offset().top+elem.outerHeight()+"px";var l=elem.offset().left+"px";var timeDiv='<div class="timePicker layui-anim layui-anim-upbit" style="left:'+l+";top:"+t+';">';timeDiv+='<div class="time-div">'+'<div class="time-info">'+'<ul class="time-day"><span>天</span><li><input type="radio" name="day" value="1">今天</li><li><input type="radio" name="day" value="2">昨天</li><li><input type="radio" name="day" value="3">明天</li></ul> '+'<ul class="time-week"><span>周</span><li><input type="radio" name="day" value="4">本周</li><li><input type="radio" name="day" value="5">上周</li><li><input type="radio" name="day" value="6">下周</li></ul> '+'<ul class="time-month"><span>月</span><li><input type="radio" name="day" value="7">本月</li><li><input type="radio" name="day" value="8">上月</li><li><input type="radio" name="day" value="9">下月</li></ul> '+'<ul class="time-quarter"><span>季度</span><li><input type="radio" name="day" value="10">本季度</li><li><input type="radio" name="day" value="11">上一季度</li><li><input type="radio" name="day" value="12">下一季度</li></ul> '+'<ul class="time-year"><span>年度</span><li><input type="radio" name="day"  value="13">本年度</li><li><input type="radio" name="day"  value="14">上一年度</li><li><input type="radio" name="day"  value="15">下一年度</li></ul>'+"</div>"+'<div class="time-custom">'+'<div class="layui-timepicker-custom"  data-role="display">'+"<span>自定义</span>"+'<i class="layui-icon layui-icon-down" ></i>'+"</div> "+'<div class="time-select">'+'<input type="text" class="layui-input" id="sTime">'+'<input type="text" class="layui-input" id="eTime">'+"</div></div>"+'<div class="time-down">'+'<div class="layui-btn layui-btn-sm layui-btn-normal"   data-role="clear">清除</div>'+'<div class="layui-btn layui-btn-sm" data-role="sure">确定</div>'+"</div>"+"</div>";timeDiv=$(timeDiv);$("body").append(timeDiv);$('[data-role="display"]').on("click",function(){$(".time-select").css("display","flex");$(this).find("i").remove()});laydate.render({elem:"#sTime",theme:"#393D49"});laydate.render({elem:"#eTime",theme:"#393D49"});var $li=$(".time-info").children().find("li");$li.on("click",function(){$(".time-info").children().find("li").removeClass("active");if($(this).children("input").is(":checked")){$(this).children("input").prop("checked",false)}else{$(this).addClass("active");$(this).children("input").prop("checked",true)}});$('[data-role="clear"]').on("click",function(){elem.val("");$(".timePicker").remove()});$('[data-role="sure"]').on("click",function(){var inputVal=$(".time-info").children().find("input:checked").val();var sTime="";var eTime="";switch(inputVal){case"1":sTime=Dayjs().startOf("day");eTime=Dayjs().endOf("day");break;case"2":sTime=Dayjs().subtract(1,"days").startOf("day");eTime=Dayjs().subtract(1,"days").endOf("day");break;case"3":sTime=Dayjs().subtract(-1,"days").startOf("day");eTime=Dayjs().subtract(-1,"days").endOf("day");break;case"4":sTime=Dayjs().startOf("week");eTime=Dayjs().endOf("week");break;case"5":sTime=Dayjs().subtract(1,"week").startOf("week");eTime=Dayjs().subtract(1,"week").endOf("week");break;case"6":sTime=Dayjs().subtract(-1,"week").startOf("week");eTime=Dayjs().subtract(-1,"week").endOf("week");break;case"7":sTime=Dayjs().startOf("month");eTime=Dayjs().endOf("month");break;case"8":sTime=Dayjs().subtract(1,"month").startOf("month");eTime=Dayjs().subtract(1,"month").endOf("month");break;case"9":sTime=Dayjs().subtract(-1,"month").startOf("month");eTime=Dayjs().subtract(-1,"month").endOf("month");break;case"10":sTime=Dayjs().startOf("quarter");eTime=Dayjs().endOf("quarter");break;case"11":sTime=Dayjs().subtract(1,"quarter").startOf("quarter");eTime=Dayjs().subtract(1,"quarter").endOf("quarter");break;case"12":sTime=Dayjs().subtract(-1,"quarter").startOf("quarter");eTime=Dayjs().subtract(-1,"quarter").endOf("quarter");break;case"13":sTime=Dayjs().startOf("year");eTime=Dayjs().endOf("year");break;case"14":sTime=Dayjs().subtract(1,"year").startOf("year");eTime=Dayjs().subtract(1,"year").endOf("year");break;case"15":sTime=Dayjs().subtract(-1,"year").startOf("year");eTime=Dayjs().subtract(-1,"year").endOf("year");break;default:sTime=$("#sTime").val();eTime=$("#eTime").val();break}if(!eTime||!sTime){return false}var timeDate="";if(inputVal){if(timeStamp){timeDate=parseInt(sTime/1e3)+" - "+parseInt(eTime/1e3)}else{timeDate=sTime.format(format)+" - "+eTime.format(format)}}else{eTime=eTime+" 23:59:59";if(timeStamp){var sTime=new Date(sTime).getTime();var eTime=new Date(eTime).getTime();timeDate=sTime/1e3+" - "+eTime/1e3}else{timeDate=Dayjs(sTime).format(format)+" - "+Dayjs(eTime).format(format)}}elem.val(timeDate);$(".timePicker").remove()})})};timePicker.prototype.hide=function(opt){$(".timePicker").remove()};var timePicker=new timePicker;$(window).scroll(function(){timePicker.hide()});exports(MOD_NAME,timePicker);exports(MOD_NAME,timePicker)});