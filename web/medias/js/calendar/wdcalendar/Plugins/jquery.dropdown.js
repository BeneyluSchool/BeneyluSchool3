﻿/// <reference path="../intellisense/jquery-1.2.6-vsdoc-cn.js" />
(function($) {
    $.fn.DhoverClass = function(className) {
        return $(this).hover(function() { $(this).addClass(className); }, function() { $(this).removeClass(className); });
    }
    function getDulyOffset(target, w, h) {
        var pos = target.offset();
        var height = target.outerHeight();
        var newpos = { left: pos.left, top: pos.top + height - 1 }
        var bw = document.documentElement.clientWidth;
        var bh = document.documentElement.clientHeight;
        if ((newpos.left + w) >= bw) {
            newpos.left = bw - w - 2;
        }
        if ((newpos.top + h) >= bh && bw > newpos.top) {
            newpos.top = pos.top - h - 2;
        }
        return newpos;
    }
    function returnfalse() { return false; };
    $.fn.dropdown = function(o) {
        var options = $.extend({
            vinputid: null,
            cssClass: "bbit-dropdown",
            containerCssClass: "dropdowncontainer",
            dropwidth: false,
            dropheight: "auto",
            autoheight: true,
            selectedchange: false,
            items: [],
            selecteditem: false,
            parse: {
                name: "list",
                render: function(parent) {
                    var p = this.target;
                    var ul = $("<ul/>");
                    if (this.items && this.items.length > 0) {
                        $.each(this.items, function() {
                            var item = this;
                            var d = $("<div/>").html(item.text);
                            var li = $("<li/>").DhoverClass("hover").append(d)
                            .click(function() { p.SelectedChanged(item); });
                            if (item.classes && item.classes != "") {
                                d.addClass(item.classes);
                            }
                            ul.append(li);
                        });
                    }
                    parent.append(ul);
                },
                items: [],
                setValue: function(item) { },
                target: null
            }
        }, o);
        var me = $(this);
        var v;
        if (options.vinputid) {
            v = $("#" + options.vinputid);
        }
        if (options.selecteditem) {
            me.val(options.selecteditem.text);
            if (v && options.selecteditem.value) {
                v.val(options.selecteditem.value);
            }
        }
        var requireCss = { height: 18, "padding-top": "1px", "padding-bottom": "1px" };
        me.css(requireCss).addClass(options.cssClass).DhoverClass("hover");
        if (!options.dropwidth) {
            options.dropwidth = me.outerWidth();
        }
        var d = $("<div/>").addClass(options.containerCssClass)
                           .css({ position: "absolute", "z-index": "999", "overflow": "auto", width: options.dropwidth, display: "none", "border": "solid 1px #555", background: "#fff" })
                           .click(function(event) { event.stopPropagation(); })
                           .appendTo($("body"));
        if (options.autoheight) {
            d.css("max-height", options.dropheight);
        }
        else {
            d.css("height", options.dropheight);
        }

        if ($.browser.msie) {
            if (parseFloat($.browser.version) <= 6) {
                var ie6hack = $("<div/>").css({ position: "absolute", "z-index": "-2", "overflow": "hidden", "height": "100%", width: "100%" });
                ie6hack.append($('<iframe style="position:absolute;z-index:-1;width:100%;height:100%;top:0;left:0;scrolling:no;" frameborder="0" src="about:blank"></iframe>'));
                d.append(ie6hack);
            }
        }
        me.click(function() {
            var m = this;
            if (d.attr("isinited") != "true") {
                options.parse.items = options.items;
                if (options.selecteditem) {
                    options.parse.setValue.call(d, options.selecteditem);
                }
                options.parse.render(d);
                d.attr("isinited", "true");
            }
            var pos = getDulyOffset(me, options.dropwidth, options.dropheight);
            d.css(pos);
            d.show();
            if ($.browser.msie) {
                if (parseFloat($.browser.version) <= 6) {
                    var h = d.height();
                    if (h > options.dropheight) {
                        d.height(options.dropheight);
                    }
                }
            }
            $(document).one("click", function(event) { d.hide(); });
            return false;
        });
        me.SelectedChanged = function(t) {
            var b = true;
            if (options.selectedchange) {
                b = options.selectedchange.apply(me, [t]);
            }
            if (b != false) {
                me.val(t.text);
                if (v && t.value) {
                    v.val(t.value);
                }
            }
            d.hide();

        };
        me.Cancel = function() {
            d.hide();
        }
        options.parse.target = me;
        return me;
    }

})(jQuery);