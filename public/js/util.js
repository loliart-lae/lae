window.util={time:{formatSeconds:function(n){var t=parseInt(n),r=0,e=0;t>60&&(r=parseInt(t/60),t=parseInt(t%60),r>60&&(e=parseInt(r/60),r=parseInt(r%60)));var a=parseInt(t)+"秒";return r>0&&(a=parseInt(r)+"分"+a),e>0&&(a=parseInt(e)+"小时"+a),a}},dialog:{confirm:function(n){mdui.confirm("你正在进入一个安全的页面，请确保你现在没有录制或者进行公开的流式媒体，否则您可能会泄漏重要信息（如用户名，密码等）",(function(){window.open(n)}))}},text:{putLyric:function(n){$.ajax({url:"/api/v1/_lyric",method:"GET",success:function(t){n(t)},error:function(){n({status:0,content:null,from:null,created_at:null})}})}}};