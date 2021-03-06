<div>
    <div class="mdui-list" id="main-list" mdui-collapse="{accordion: true}">
        @guest
            <a class="mdui-list-item mdui-ripple umami--click--main-link" href="{{ route('index') }}/">
                <span class="mdui-list-item-icon mdui-icon material-icons-outlined">home</span>
                <div class="mdui-list-item-content">{{ config('app.name') }}</div>
            </a>
            <a class="mdui-list-item mdui-ripple umami--click--guest-login" href="{{ route('login') }}">
                <span class="mdui-list-item-icon mdui-icon material-icons-outlined">login</span>
                <div class="mdui-list-item-content">登录或注册</div>
            </a>
            <a class="mdui-list-item mdui-ripple umami--click--why-begin" href="{{ route('why_begin') }}">
                <span class="mdui-list-item-icon mdui-icon material-icons-outlined">volunteer_activism</span>
                <div class="mdui-list-item-content">我们的初心</div>
            </a>
        @else
            <a class="mdui-list-item mdui-ripple umami--click--user-index" href="{{ route('user.index') }}">
                <span class="mdui-list-item-icon mdui-icon material-icons-outlined">account_circle</span>
                <div class="mdui-list-item-content"><small>
                        {{ Auth::user()->name }} / <span class="userBalance" id="userBalance"
                            style="display: contents;">{{ Auth::user()->balance }}</span></small></div>
            </a>

            <a class="mdui-list-item mdui-ripple umami--click--project" href="{{ route('projects.index') }}">
                <span class="mdui-list-item-icon mdui-icon material-icons-outlined">groups</span>
                <div class="mdui-list-item-content">项目管理</div>
            </a>

            <a class="mdui-list-item mdui-ripple umami--click--server-monitor" href="{{ route('lxd.index') }}">
                <span class="mdui-list-item-icon mdui-icon material-icons-outlined">all_inbox</span>
                <div class="mdui-list-item-content">应用容器</div>
            </a>

            <a class="mdui-list-item mdui-ripple umami--click--vm" href="{{ route('virtualMachine.index') }}">
                <span class="mdui-list-item-icon mdui-icon material-icons-outlined">developer_board</span>
                <div class="mdui-list-item-content">云虚拟机</div>
            </a>

            <a class="mdui-list-item mdui-ripple umami--click--game-server" href="{{ route('gameServer.index') }}">
                <span class="mdui-list-item-icon mdui-icon material-icons-outlined">games</span>
                <div class="mdui-list-item-content">Game Server</div>
            </a>

            <a class="mdui-list-item mdui-ripple umami--click--game-server" href="{{ route('tunnels.index') }}">
                <span class="mdui-list-item-icon mdui-icon material-icons-outlined">swap_horiz</span>
                <div class="mdui-list-item-content">穿透隧道</div>
            </a>

            <a class="mdui-list-item mdui-ripple umami--click--game-server" href="{{ route('fastVisit.index') }}">
                <span class="mdui-list-item-icon mdui-icon material-icons-outlined">forward</span>
                <div class="mdui-list-item-content">快捷访问</div>
            </a>

            <a class="mdui-list-item mdui-ripple umami--click--staticPage" href="{{ route('staticPage.index') }}">
                <span class="mdui-list-item-icon mdui-icon material-icons-outlined">storages</span>
                <div class="mdui-list-item-content">静态站点</div>
            </a>
            <a class="mdui-list-item mdui-ripple umami--click--cyberpanel" href="{{ route('cyberPanel.index') }}">
                <span class="mdui-list-item-icon mdui-icon material-icons-outlined">public</span>
                <div class="mdui-list-item-content">CyberPanel 站点</div>
            </a>

            {{-- <div class="mdui-collapse-item parent-menu">
                <div class="mdui-collapse-item-header mdui-list-item mdui-ripple">
                    <span class="mdui-list-item-icon mdui-icon material-icons-outlined">public</span>
                    <div class="mdui-list-item-content">Web 服务</div>
                    <i class="mdui-collapse-item-arrow mdui-icon material-icons">keyboard_arrow_down</i>
                </div>
                <div class="mdui-collapse-item-body mdui-list"> --}}
            {{-- <a class="mdui-list-item mdui-ripple umami--click--easypanel" href="{{ route('easyPanel.index') }}">
                        <div class="mdui-list-item-content">EasyPanel 站点</div>
                    </a> --}}
            {{-- </div>
            </div> --}}

            {{-- <a class="mdui-collapse-item-header mdui-list-item mdui-ripple umami--click--bridge"
                href="{{ route('bridge.index') }}">
                <span class="mdui-list-item-icon mdui-icon material-icons-outlined">social_distance</span>
                <div class="mdui-list-item-content">Transfer Bridge</div>
            </a> --}}

            {{-- <a class="mdui-collapse-item-header mdui-list-item mdui-ripple umami--click--bridge"
                href="{{ route('live.index') }}">
                <span class="mdui-list-item-icon mdui-icon material-icons-outlined">ondemand_video</span>
                <div class="mdui-list-item-content">流媒体节目</div>
            </a> --}}

            @php($admins = config('admin.admin_users'))
            @if (in_array(Auth::user()->email, $admins))
                <div class="mdui-collapse-item parent-menu">
                    <div class="mdui-collapse-item-header mdui-list-item mdui-ripple">
                        <span class="mdui-list-item-icon mdui-icon material-icons-outlined">admin_panel_settings</span>
                        <div class="mdui-list-item-content">管理员工具</div>
                        <i class="mdui-collapse-item-arrow mdui-icon material-icons">keyboard_arrow_down</i>
                    </div>
                    <div class="mdui-collapse-item-body mdui-list">
                        <span class="mdui-list-item mdui-ripple" onclick="window.open('/admin')">
                            <div class="mdui-list-item-content">总览</div>
                        </span>
                        <span class="mdui-list-item mdui-ripple" onclick="window.open('/horizon')">
                            <div class="mdui-list-item-content">队列</div>
                        </span>
                        <span class="mdui-list-item mdui-ripple" onclick="window.open('/telescope')">
                            <div class="mdui-list-item-content">调试</div>
                        </span>
                    </div>
                </div>
            @endif

            <a class="mdui-list-item mdui-ripple umami--click--document" href="{{ route('documents.index') }}">
                <span class="mdui-list-item-icon mdui-icon material-icons-outlined">description</span>
                <div class="mdui-list-item-content">文档中心</div>
            </a>

            <a class="mdui-list-item mdui-ripple umami--click--forum" target="_blank" href="https://f.lightart.top">
                <span class="mdui-list-item-icon mdui-icon material-icons-outlined">question_answer</span>
                <div class="mdui-list-item-content">社区论坛</div>
            </a>

            <a class="mdui-list-item mdui-ripple umami--click--contributes" href="{{ route('contributes') }}">
                <span class="mdui-list-item-icon mdui-icon material-icons-outlined">token</span>
                <div class="mdui-list-item-content">贡献者</div>
            </a>

            <a class="mdui-list-item mdui-ripple umami--click--forum" href="{{ route('user.blocked') }}">
                <span class="mdui-list-item-icon mdui-icon material-icons-outlined">block</span>
                <div class="mdui-list-item-content">封神榜</div>
            </a>

            <a onclick="event.preventDefault();document.getElementById('logout-form').submit();"
                class="mdui-list-item mdui-ripple umami--click--logout" target="_blank" href="https://f.lightart.top">
                <span class="mdui-list-item-icon mdui-icon material-icons-outlined">
                    logout
                </span>
                <div class="mdui-list-item-content">退出登录</div>
            </a>

            <form style="display: none" id="logout-form" action="{{ route('logout') }}" method="POST"
                class="d-none">
                @csrf
            </form>
        @endguest
    </div>
</div>
