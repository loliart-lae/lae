@extends('layouts.app')

@section('title', '新建 Tunnel 隧道')

@section('content')
    <div class="mdui-typo-display-2">新建 Tunnel 隧道</div>

    <p>在选定的项目中新建 Tunnel 隧道</p>

    <form method="post" action="{{ route('tunnels.store') }}">
        @csrf

        <div class="mdui-tab mdui-tab-scrollable mdui-m-t-1" id="page-tab" mdui-tab>
            <a href="#choose-project" class="mdui-ripple">选择项目</a>
            <a href="#choose-server" class="mdui-ripple">选择服务器</a>
            <a href="#choose-protocol" class="mdui-ripple">设定协议</a>
            <a href="#choose-addr" class="mdui-ripple">设定地址</a>
            <a href="#choose-name" class="mdui-ripple">设定名称</a>
        </div>

        <div id="choose-project">
            <x-choose-project-form />
        </div>

        <div id="choose-server">
            <div class="mdui-row mdui-p-t-4 mdui-p-b-2 mdui-p-l-1">
                <span class="mdui-typo-headline">选择 Tunnel 隧道 服务器</span>
                <p class="mdui-typo-subheading">Tunnel 隧道服务器影响着访问速度以及连通性，稳定性，以及基础价格。</p>
            </div>

            <div class="mdui-table-fluid">
                <table class="mdui-table mdui-table-hoverable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>名称</th>
                            <th>共享带宽</th>
                            <th>积分/分钟</th>
                            <th>月预估</th>
                            <th>选择</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php($i = 1)
                        @foreach ($servers as $server)
                            <tr>
                                <td nowrap>{{ $i++ }}</td>
                                <td nowrap>{{ $server->name }}</td>
                                <td nowrap>{{ $server->network_limit }} Mbps</td>
                                <td nowrap>{{ $server->price }}</td>
                                <td nowrap>
                                    {{ number_format(($server->price * 44640) / config('billing.exchange_rate'), 2) }}
                                    元 / 月</td>

                                <td>
                                    <label class="mdui-radio">
                                        <input type="radio" value="{{ $server->id }} " name="server_id"
                                            @if ($i == 2) checked @endif required />
                                        <i class="mdui-radio-icon"></i>

                                    </label>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>


        <div id="choose-protocol">
            <div class="mdui-row mdui-p-t-4 mdui-p-l-1 mdui-m-b-2">
                <span class="mdui-typo-headline">隧道协议</span>
                <p>根据您的使用场景以及应用选择。</p>
                <select name="protocol" class="mdui-select" id="protocol" mdui-select mdui-select="options" required>
                    <option value="http">HTTP - 适合不加密，明文传输的网页浏览业务。</option>
                    <option value="https">HTTPS - 适合加密，对安全性较强的业务。</option>
                    <option value="tcp">TCP - 即时通讯或者游戏等对可靠性要求较高的业务。</option>
                    <option value="udp">UDP - 适合数据可靠性较低的业务。</option>
                    <option value="xtcp">XTCP - 免费！P2P传输，需要双方都启动客户端，并且不能保证穿透成功。</option>
                </select>
            </div>
        </div>

        <div id="choose-addr">
            <div class="mdui-row mdui-p-t-4">
                <div class="mdui-col-xs-6">
                    <span class="mdui-typo-headline">内网地址</span>
                    <p>被映射主机的地址，比如 127.0.0.1:80</p>
                    <div class="mdui-textfield mdui-textfield-floating-label">
                        <label class="mdui-textfield-label">内网地址</label>
                        <input class="mdui-textfield-input" type="text" name="local_address"
                            value="{{ old('local_address') }}" required />
                    </div>
                </div>

                <div class="mdui-col-xs-6" id="remote-input" style="display: none">
                    <span class="mdui-typo-headline">公网端口</span>
                    <p>公网访问时所使用的端口。</p>
                    <div class="mdui-textfield mdui-textfield-floating-label">
                        <label class="mdui-textfield-label">公网端口</label>
                        <input class="mdui-textfield-input" type="text" name="remote_port"
                            value="{{ old('remote_port') }}" />
                    </div>
                </div>

                <div class="mdui-col-xs-6" id="domain-input">
                    <span class="mdui-typo-headline">域名</span>
                    <p>创建完成后将此域名 CNAME 记录到对应服务器的域名。<br /></p>
                    <div class="mdui-textfield mdui-textfield-floating-label">
                        <label class="mdui-textfield-label">域名</label>
                        <input class="mdui-textfield-input" type="text" name="custom_domain"
                            value="{{ old('custom_domain') }}" />
                    </div>
                </div>

                <div class="mdui-col-xs-6" id="sk-input" style="display: none">
                    <span class="mdui-typo-headline">XTCP 密钥</span>
                    <p>只允许字母、数字，短破折号（-）和下划线（_）,至少 3 位，最多 15 位并且无法修改。</p>
                    <div class="mdui-textfield mdui-textfield-floating-label">
                        <label class="mdui-textfield-label">XTCP 密钥</label>
                        <input class="mdui-textfield-input" type="text" name="sk" value="{{ old('sk') }}" />
                    </div>
                </div>
            </div>
        </div>

        <div id="choose-name">
            <div class="mdui-row mdui-p-t-4 mdui-p-l-1">
                <span class="mdui-typo-headline">隧道的名称</span>
                <p>只允许字母、数字，短破折号（-）和下划线（_）,至少 3 位，最多 15 位。该名称用于标识。</p>
                <div class="mdui-textfield mdui-textfield-floating-label">
                    <label class="mdui-textfield-label">隧道名称</label>
                    <input class="mdui-textfield-input" type="text" name="name" value="{{ old('name') }}" required />
                </div>
            </div>

            <div class="mdui-row mdui-p-y-2">
                <button type="submit"
                    class="mdui-float-right mdui-btn mdui-color-theme-accent mdui-ripple umami--click--new-tunnel">新建</button>
            </div>
        </div>




        <div class="mdui-typo" style="text-align: right;margin-top: 10px"><small class="mdui-clearfix">
                注意：每分钟价格 = 地区服务器基础价格<br />
                Tunnel 隧道 一旦创建成功后将无法修改<br />
                XTCP 免费，带宽受限于你的网络上行速度。
            </small></div>
    </form>

    <script>
        $('#protocol').change(function() {
            let val = $('#protocol').val()
            if (val == 'http' || val == 'https') {
                $('#sk-input').hide()
                $('#remote-input').hide()
                $('#domain-input').show()
            } else if (val == 'tcp' || val == 'udp') {
                $('#sk-input').hide()
                $('#domain-input').hide()
                $('#remote-input').show()
            } else if (val == 'xtcp') {
                $('#remote-input').hide()
                $('#domain-input').hide()
                $('#sk-input').show()
            }
        })
    </script>
@endsection
