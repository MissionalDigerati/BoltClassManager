$(function(){
    var datetime_from_string = function(str){
        var months = ["jan","feb","mar","apr","may","jun","jul",
                      "aug","sep","oct","nov","dec"];
        var pattern = "([a-zA-Z]{3})\\s*(\\d{2}),\\s*(\\d{4})\\s*@{1}\\s*(\\d{2}):{1}(\\d{2})\\s*([a-zA-Z]{2})";
        var re = new RegExp(pattern);
        var dateParts = re.exec(str).slice(1);

        var year = dateParts[2];
        var month = $.inArray(dateParts[0].toLowerCase(), months);
        var day = dateParts[1];
        var hour = dateParts[3];
        var minutes = dateParts[4];
        var meridian = dateParts[5];
        if (meridian == "pm") {
            hour = parseInt(hour)+12;
        };
        return new Date(year, month, day, hour, minutes);
    };
    var table = $("table").stupidtable({
        "datetime":function(a,b) {

          aDate = datetime_from_string(a);
          bDate = datetime_from_string(b);

          return aDate - bDate;
        }
    });
    table.on("aftertablesort", function (event, data) {
        var th = $(this).find("th");
        th.find(".arrow").remove();
        var dir = $.fn.stupidtable.dir;

        var arrow = data.direction === dir.ASC ? "icon-chevron-up" : "icon-chevron-down";
        th.eq(data.column).append('<i class="' + arrow +' arrow" style="padding-left: 5px;"></i>');
    });
    $('th.sort_on_load').click();
    $('form.delete_from_class').submit(function(event) {
        var c = confirm($(this).attr('data-confirm'));
        return c;
    });
});