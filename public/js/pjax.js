(()=>{var t=$("#top-tab").height(),i=$("#top-space").height(),o=function(){var t=window.location.protocol+"//"+window.location.host+window.location.pathname;$("#main-list a[href='"+t+"']").length>0&&($("#main-list .mdui-list-item").removeClass("mdui-list-item-active"),$("#main-list a[href='"+t+"']").addClass("mdui-list-item-active"),$("#backMain").attr("href",t))},n=function(){"/"!=window.location.pathname?($("#top-tab").css("min-height",0),$("#top-tab").css("height",0),$("#top-space").css("min-height",0),$("#top-space").css("height",0)):($("#top-tab").css("min-height",t),$("#top-tab").css("height",t),$("#top-space").css("min-height",i),$("#top-space").css("height",i))};$.ajaxSetup({headers:{"X-CSRF-TOKEN":$('input[name="_token"]').val()}}),$.pjax.defaults.timeout=1500,window.addEventListener("online",close_offline_tip),window.addEventListener("offline",showOfflineTip),$(document).pjax("a",".pjax-container"),$(document).on("pjax:clicked",(function(){$("#turn").css("animation-play-state","running")})),$(document).on("pjax:timeout",(function(t){$("#main").css("opacity",0),t.preventDefault()})),$(document).on("pjax:complete",(function(t){o(),$("#main").css("height","auto"),$("#main").css("overflow","unset"),$("#main").css("opacity",1),$("#turn").css("animation-play-state","paused"),$("#thisLink").attr("href",window.location.href),n();var i=document.title;i=i.replace(" - "+$("#app-name").text(),""),$("#top-title").text(i),mdui.mutation()})),window.history&&window.history.pushState&&(window.onpopstate=function(){o()}),o();var e=$("#main").offset().top;$(window).scroll((function(){var t=document.documentElement.scrollTop||document.body.scrollTop;(t>=e?1:0)?$("#bottom-fab").removeClass("mdui-fab-hide"):$("#bottom-fab").addClass("mdui-fab-hide")})),n()})();