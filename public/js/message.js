var nowShow = null;
var streaming_div_height = ($('#streaming_div').height() + 30) + 'px';
$('#streaming_div').height('0px')
$('#streaming_div').show('0px')

setInterval(function () {
    let updateCount = 0
    let date = new Date()
    let startTime = Date.parse(date)
    if (localStorage.getItem('startTime') == null) {
        localStorage.setItem('startTime', startTime)
    }
    current = localStorage.getItem('startTime')
    if (startTime - current >= 10000) {
        // 立即更新localStorage，然后获取通知
        localStorage.setItem('startTime', startTime)

        $.ajax({
            type: 'GET',
            url: '/messages/unread',
            dataType: 'json',
            success: function (data) {
                close_offline_tip()
                var currentBalance = parseFloat($('#userBalance').text())
                if (currentBalance != data.balance && updateCount == 0) {
                    mdui.snackbar({
                        message: '账户积分已更新为:' + data.balance,
                        position: 'right-bottom'
                    })
                    $({
                        // 起始值
                        countNum: currentBalance
                    }).animate({
                        // 最终值
                        countNum: data.balance
                    }, {
                        // 动画持续时间
                        duration: 2000,
                        easing: "linear",
                        step: function () {
                            // 设置每步动画计算的数值
                            $('.userBalance').text(Math.floor(this.countNum))
                        },
                        complete: function () {
                            // 设置动画结束的数值
                            $('.userBalance').text(this.countNum)
                        }
                    })
                }
                updateCount++
                $('.userBalance').html(data.balance)
                for (var i = 0; i < data.data.length; i++) {
                    if (data.data.length != 0) {
                        mdui.snackbar({
                            message: data.data[i].content,
                            position: 'right-bottom'
                        })
                    }
                }

                if (data.streaming != null) {
                    $('.global-time-river').addClass('mdui-text-color-green')
                    if (data.streaming.id != nowShow) {
                        $('#streaming_div').css('height', streaming_div_height)
                        $('#live_name').text(data.streaming.name)

                        mdui.snackbar({
                            message: '节目 ' + data.streaming.name + ' 已开始。',
                            position: 'right-bottom'
                        })
                        nowShow = data.streaming.id

                        if (Hls.isSupported()) {
                            var hls = new Hls()
                            hls.loadSource(url)
                            hls.attachMedia(video_streaming)
                            hls.on(Hls.Events.MANIFEST_PARSED, function () {
                                video_streaming.play()
                            })
                        } else if (video_streaming.canPlayType('application/vnd.apple.mpegurl')) {
                            video_streaming.src = url
                            video_streaming.addEventListener('canplay', function () {
                                video_streaming.play()
                            })
                        }
                    }

                } else {
                    nowShow = null
                    $('.global-time-river').removeClass('mdui-text-color-green')
                    $('#streaming_div').css('height', '0px')


                }
            },
            error: function (data) {
                showOfflineTip()
            }
        })
    }
}, 1000)
