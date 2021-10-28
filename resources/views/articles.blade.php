@extends('layouts.app')

@section('title', '博文')

@section('content')
    <style>
        .goto_url {
            cursor: pointer;
        }

    </style>
    @php($admins = config('admin.admin_users'))

    <div class="mdui-typo">
        <h4><a href="{{ route('main') }}">我的时间河</a>&nbsp;|&nbsp;<a href="{{ route('global') }}">全站时间河</a>&nbsp;|&nbsp;博文</h4>
    </div>

    <div id="masonry" class="mdui-row">
        @foreach ($articles as $article)
            <div class="poll mdui-col-sm-4 mdui-col-xs-12 mdui-m-t-1 goto_url" href="{{ $article->link }}">
                <div class="mdui-card mdui-hoverable user_{{ $article->user->id }}_status" style="margin-top: 5px">
                    <div class="mdui-card-header">
                        <img class="mdui-card-header-avatar"
                            src="{{ config('app.gravatar_url') }}/{{ md5(strtolower($article->user->email)) }}" />
                        <div class="mdui-card-header-title mdui-typo">
                            @if (is_null($article->user->website))
                                {{ $article->user->name }}
                            @else
                                <a target="_blank" href="{{ $article->user->website }}">{{ $article->user->name }}</a>
                            @endif
                            <small> /
                                {{ \Carbon\Carbon::parse($article->datetime)->diffForHumans() }}</small>
                            <div
                                style="display: inline;
                                                                                                                                                                        position: absolute;
                                                                                                                                                                        right: 16px;
                                                                                                                                                                        margin-top: 3px;cursor: pointer">
                                @if (in_array($article->user->email, $admins))
                                    <span mdui-tooltip="{content: '官方人员'}"
                                        class="mdui-icon material-icons-outlined material-icons-outlined verified_user">
                                        verified_user
                                    </span>
                                @endif
                                <span class="follow_{{ $article->user->id }}">
                                    @if ($display ?? '' != 0)
                                        @if ($article->user->id == Auth::id())
                                            <i mdui-tooltip="{content: '这是你'}"
                                                class="mdui-text-color-theme mdui-icon material-icons-outlined"
                                                onclick="$(this).addClass('animate__animated animate__tada')">account_circle</i>
                                        @elseif (in_array($article->user->id, $ids))
                                            <i onclick="$(this).addClass('animate__animated animate__pulse animate__infinite');toggleFollow({{ $article->user->id }})"
                                                class="mdui-text-color-theme mdui-icon material-icons-outlined umami--click--unfollow-user">favorite</i>
                                        @else
                                            <i onclick="$(this).addClass('animate__animated animate__pulse animate__infinite');toggleFollow({{ $article->user->id }})"
                                                class="mdui-text-color-black-secondary mdui-icon material-icons-outlined umami--click--follow-user">favorite</i>
                                        @endif
                                    @endif
                                </span>

                            </div>
                        </div>
                        <div class="mdui-card-header-subtitle">{{ $article->user->bio ?? null }}</div>
                    </div>

                    <div class="mdui-card-content">
                        <div class="mdui-typo-title">{{ $article->title }}</div>
                        <br />
                        {{ strip_tags($article->description) }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{ $articles->links() }}

    <script>
        var $container = $('#masonry')
        masonry_resize()

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

        $('.goto_url').click(function() {
            window.open($(this).attr('href'))
        })
    </script>

@endsection
