@guest
<div class="mdui-typo-display-1">时间长河</div>
@endguest

@if ($feed_items->count() > 0)
<div id="masonry" class="mdui-row">
    @php($admins = config('admin.admin_users'))

    @foreach ($feed_items as $status)
    <div class="poll mdui-col-sm-4 mdui-col-xs-12 mdui-m-t-1">
        <div class="mdui-card mdui-hoverable user_{{ $status->user_id }}_status" style="margin-top: 5px">
            <div class="mdui-card-header">
                <img class="mdui-card-header-avatar"
                    src="{{ config('app.gravatar_url') }}/{{ md5(strtolower($status->user->email)) }}" />

                <div class="mdui-card-header-title">
                    <a
                        href="@auth{{ route('user.show', $status->user_id) }}@else{{ route('global.user.show', $status->user_id) }}@endauth">{{
                        $status->user->name }}</a>
                    <small> /
                        {{ $status->created_at->diffForHumans() }}</small>
                    <div style="display: inline;
                                position: absolute;
                                right: 16px;
                                margin-top: 3px;cursor: pointer">
                        @if (in_array($status->user->email, $admins))
                        <span mdui-tooltip="{content: '官方人员'}"
                            class="mdui-icon material-icons-outlined material-icons-outlined verified_user">
                            verified_user
                        </span>
                        @endif
                        @auth
                        <span class="follow_{{ $status->user->id }}">
                            @if ($display ?? '' != 0)
                            @if ($status->user->id == Auth::id())
                            <i mdui-tooltip="{content: '这是你'}"
                                class="mdui-text-color-theme mdui-icon material-icons-outlined"
                                onclick="$(this).addClass('animate__animated animate__tada')">account_circle</i>
                            @elseif (in_array($status->user->id, $ids))
                            <i mdui-tooltip="{content: '已关注'}"
                                onclick="$(this).addClass('animate__animated animate__pulse animate__infinite');toggleFollow({{ $status->user->id }})"
                                class="mdui-text-color-theme mdui-icon material-icons-outlined umami--click--unfollow-user">favorite</i>
                            @else
                            <i mdui-tooltip="{content: '关注'}"
                                onclick="$(this).addClass('animate__animated animate__pulse animate__infinite');toggleFollow({{ $status->user->id }})"
                                class="mdui-text-color-black-secondary mdui-icon material-icons-outlined umami--click--follow-user">favorite</i>
                            @endif
                            @endif
                        </span>
                        @endauth
                    </div>
                </div>
                <div class="mdui-card-header-subtitle">{{ $status->user->bio ?? null }}</div>
            </div>
            <div class="mdui-card-content mdui-p-t-1">
                <div id="log_{{ $status->id }}"></div>
                <textarea id="log_{{ $status->id }}_content"
                    style="display:none;">{!! e($status->content) !!}</textarea>
                <script>
                    $(function() {
                                var log_view
                                $('#log_{{ $status->id }}').html(null)
                                log_view = editormd.markdownToHTML("log_{{ $status->id }}", {
                                    markdown: $('#log_{{ $status->id }}_content').html(),
                                    tocm: true,
                                    emoji: true,
                                    taskList: true,
                                })
                            })
                </script>
            </div>
            <div class="mdui-card-actions">
                @auth
                <button id="status_{{ $status->id }}" onclick="toggleLike({{ $status->id }})"
                    class="mdui-btn mdui-ripple mdui-btn-icon">
                    @if (is_null($status->like))
                    <i class="mdui-icon material-icons-outlined umami--click--like" style="color: unset">star_border</i>
                    @elseif ($status->like->is_liked)
                    <i style="color:#36a6e8" class="mdui-icon material-icons-outlined umami--click--unlike">star</i>
                    @else
                    <i class="mdui-icon material-icons-outlined" style="color: unset">star_border</i>
                    @endif
                </button>
                @endauth
                <a href="@auth{{ route('status.show', $status->id) }}@else{{ route('timeRiver.show', $status->id) }}@endauth"
                    class="mdui-btn mdui-ripple">@php($replies = count($status->replies)) @if ($replies > 0) {{ $replies
                    }}条 @else 没有 @endif
                    回复</a>
                @auth
                @can('destroy', $status)
                <form style="display: initial;" action="{{ route('status.destroy', $status->id) }}" method="POST"
                    onsubmit="return confirm('确定要删除吗？删除后动态将会永远被埋没到长河中。');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="mdui-btn mdui-ripple umami--click--status-delete">删除</button>
                </form>
                @endcan
                @endauth
            </div>
        </div>
    </div>
    @endforeach
</div>
<script>
    var $container = $('#masonry')
    var url = window.location.pathname

    function masonry_resize() {
        $container.masonry({
            itemSelector: '.poll',
        })
    }

    $(window).ready(function() {
        setTimeout(function() {
            masonry_resize()
        }, 500)
    })

    clearInterval(resize_poll)
    var resize_poll = setInterval(function() {
        if (window.location.pathname != url) {
            clearInterval(resize_poll)
        } else {
            masonry_resize()
        }
    }, 500)

    // $('.smoove').smoove({
    //     offset: '3%'
    // })
</script>
<div class="mdui-m-t-2 mdui-m-b-4">
    {{ $feed_items->links() }}
</div>
<script>
    function toggleLike(id) {
        $.ajax({
            type: 'PUT',
            url: `{{ route('status.like') }}?id=${id}`,
            data: {
                'toggle': 'toggle'
            },
            dataType: 'json',
            success: function(data) {
                if (data.status == 1) {
                    $('#status_' + id).html(`<i class="mdui-icon material-icons-outlined">star</i>`)
                    $('#status_' + id + ' i').css('color', '#36a6e8')
                } else {
                    $('#status_' + id).html(`<i class="mdui-icon material-icons-outlined">star_border</i>`)
                }
            },
            error: function(data) {
                mdui.snackbar({
                    message: '暂时无法点赞。',
                    position: 'bottom'
                })
            }
        })
    }

    function sleep(time) {
        return new Promise(function(resolve) {
            setTimeout(resolve, time);
        });
    }

    function toggleFollow(id) {

        $.ajax({
            type: 'PUT',
            url: `{{ route('user.toggleFollow') }}?id=${id}`,
            data: {
                'toggle': 'toggle'
            },
            dataType: 'json',
            success: function(data) {
                if (data[0] == true) {
                    $('.follow_' + id).html(
                        `<i onclick="$(this).addClass('animate__animated animate__pulse animate__infinite');toggleFollow(${id})"
                                            class="follow_${id} mdui-text-color-theme mdui-icon material-icons-outlined animate__heartBeat">favorite</i>`
                    )
                } else {
                    var user_statuses = $('.user_' + id + '_status');
                    user_statuses.addClass('animate__animated animate__backOutDown')
                    setTimeout(function() {
                        user_statuses.remove()
                        $('#masonry').masonry()
                    }, 1000)


                    $('.follow_' + id).html(
                        `<i onclick="$(this).addClass('animate__animated animate__pulse animate__infinite');toggleFollow(${id})" class="follow_${id} mdui-text-color-black-secondary mdui-icon material-icons-outlined animate__animated animate__flip">favorite</i>`
                    )
                }

                if (data['msg'] != undefined) {
                    mdui.snackbar({
                        message: data['msg'],
                        position: 'bottom'
                    })
                }
            },
            error: function(data) {
                mdui.snackbar({
                    message: '暂时无法切换关注状态。',
                    position: 'bottom'
                })
            }
        })
    }
</script>

@else
<p>还没有人出现在你的时间长河中。</p>
@endif
