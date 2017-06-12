/**
 * Created by Maxime on 25/06/2015.
 */

tinymce.PluginManager.add("emojis",function(t,e){
    function a(){
        var t;
        return t='<table role="list" class="mce-grid">',
            tinymce.each(i,function(a){
                t+="<tr>",tinymce.each(a,function(a){
                    var i=e+"/img/16x16/"+a+".png";
                    var j=e+"/img/36x36/"+a+".png";
                    t+='<td><a href="#" data-mce-url="'+j+'" data-mce-alt="'+a+'" tabindex="-1" role="option" aria-label="'+a+'"><img src="'+i+'" style="width: 16px; height: 16px;padding:1px 1px 1px 1px;" role="presentation" /></a></td>'
                }),
                    t+="</tr>"}),t+="</table>"
    }

    var i= new Array(21);
    for(var k= 0;k<21;k++)
        i[k]=new Array(20);
    var h=1;
    for(var j=0;j<21;j++)
        for(var k=0;k<20;k++) {
            i[j][k] = '' + h;
            h++;
        }
    t.addButton("emojis",{
        type:"panelbutton",
        panel:{role:"application",
            autohide:1,
            html:a,
            onclick:function(e){
                var a=t.dom.getParent(e.target,"a");
                a&&(t.insertContent('<img src="'+a.getAttribute("data-mce-url")+'" alt="'+a.getAttribute("data-mce-alt")+'" />'))
            }
        },
        tooltip:"Emoticons"})
});
