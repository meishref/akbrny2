<!DOCTYPE html >
<html lang="ar" dir="rtl" >
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title',config('app.name', 'اخبرني'))</title>

    <meta name="description" content="@yield('description','موقع وتطبيق لاستقبال الرسائل المجهولة و الآراء الصادقة من المتابعين والمعجبين على شبكات التواصل الاجتماعي')">

    <meta name="keywords" content="@yield('keywords','أخبرني , تطبيق اخبرني,برنامج اخبرني,أخبرني ,برنامج اخبرني , أخبرني للاندرويد,اخبرني,برنامج اخبرني')">
    <meta name="author" content="akbrny.com">

    <!-- twitter cards -->
    <meta name="twitter:card" content="summary"/>
    <meta name="twitter:site" content="@akbrny.com"/>
    <meta name="twitter:creator" content="@akbrny"/>
    <meta name="twitter:title" content="@yield('twitter_title','اخبرني')"/>
    <meta name="twitter:image:src" content="@yield('twitter_img',asset('img/logo120.png'))"/>
    <meta name="twitter:description" content="@yield('twitter_desc','موقع اخبرني لاستقبال الرسائل المجهولة')"/>
    <!-- end twitter cards -->

    <!-- facebook open graph -->
    <meta property="og:type" content="website"/>
    <meta property="og:site_name" content="@yield('fb_title','اخبرني')"/>
    <meta property="og:locale" content="ar_AR"/>
    <meta property="og:locale:alternate" content="ar_AR"/>
    <meta property="og:url" content="@yield('fb_url','http://akbrny.com')"/>
    <meta property="og:title" content="@yield('fb_title','اخبرني')"/>
    <meta property="og:description" content="@yield('fb_desc','موقع اخبرني لاستقبال الرساءل المجهولة')"/>
    <meta property="og:image" content="@yield('twitter_img',asset('img/logo120.png'))"/>
    <meta property="og:image:width" content="256"/>
    <meta property="og:image:height" content="256"/>
    <!-- end facebook open graph -->


    <link rel="stylesheet" href="{{asset('style/css/owl.carousel.css')}}">
    <link rel="stylesheet" href="{{asset('style/css/owl.theme.css')}}">
    <link href="{{asset('style/css/bootstrap.css')}}" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="{{asset('style/css/all.min.css')}}">
    <link rel="stylesheet" href="{{asset('style/css/fontawesome.min.css')}}">
    <link href="{{asset('style/md/css/ripples.min.css')}}" rel="stylesheet">
    <link href="{{asset('style/md/css/animate.css')}}" rel="stylesheet">
    <!-- include the style -->
    <link rel="stylesheet" href="{{asset('style/app/css/alertify.rtl.css')}}" />
    <link href="{{asset('style/css/animate.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('style/css/style.css')}}" rel="stylesheet" type="text/css" />
    @yield('head')

    <!--[if lt IE 7]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <!--[if lt IE 8]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>
<body>
<!--start navbar-->
<div class="navbar-more-overlay"></div>
<nav class="navbar navbar-default navbar-fixed-top animate">
    <div class="container navbar-more visible-xs">
        <ul class="nav navbar-nav">

        </ul>
    </div>

    <div class="container">
        <div class="navbar-header hidden-xs">
            <a class="navbar-brand" href="{{route('home')}}">اخبرني</a>
        </div>
        <ul class="nav navbar-nav navbar-left mobile-bar">
            @guest()
                <li>
                    <a href="{{route('register')}}">
                        <span class="menu-icon fa fa-plus"></span>
                        إنشاء حساب جديد
                    </a>
                </li>
                <li>
                    <a href="{{route('login')}}">
                        <span class="menu-icon fa fa-sign-in-alt"></span>
                        تسجيل الدخول
                    </a>
                </li>
            @else
                <!-- Notification  -->
            <li>
                <a href="{{route('user.index')}}">
                    <span class="menu-icon fa fa-comments"> @if($num_message_unread)<span class="badge btn-danger">{{$num_message_unread}}</span> @endif </span>
                     الرسائل
                </a>
            </li>
            <li>
                <a data-toggle="collapse" href="#Search"
                   aria-expanded="false" aria-controls="collapseExample">
                    <span class="menu-icon fa fa-search"></span>
                    بحث
                </a>

            </li>

            <li>
                <a href="{{route('user.settings')}}">
                    <span class="menu-icon fa fa-user"></span>
                    حسابي
                </a>
            </li>

            <li>
                <a href="{{route('user.notification')}}">
                    <span class="menu-icon fa fa-bell"></span>
                    التنبيهات
                </a>
            </li>

            @endguest
        </ul>

    </div>
</nav>
<div class="collapse" id="Search">
    <div class="well">
        <form class="navbar-form">
            <div class="form-group">
                <input type="search" id="search_input" class="form-control" placeholder="بحث ...">
            </div>
         </form>
        <div id="search_result">

        </div>

    </div>
</div>
  @yield('content')
<footer class="text-center">
    <div class="container">
        <div class="row">
            <p>تابعنا على</p>
            <div class="sochal">
                <a target="_blank" href="http://twitter.com/akbrny"><i class="fab fa-twitter"></i></a>
                <a target="_blank" href="http://instagram.com/akbrny"><i class="fab fa-instagram "></i></a>
                <a target="_blank" href="http://youtube.com/akbrny"><i class="fab fa-youtube "></i></a>
            </div>
            <br>
            <a href="{{route('pages.terms')}}">شروط الاستخدام</a> -
            <a href="{{route('pages.privacy-policy')}}">سياسة الخصوصية</a> -
            <a href="{{route('pages.contact')}}">اتصل بنا</a>

        </div>
    </div>
    <div class="coppy">
        <p>اخبرني © {{date('Y')}} <i class="fas fa-heart"></i></p>
    </div>
</footer>
<!--end footer-->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
<link rel="manifest" href="{{asset('manifest.json')}}">
<script src="https://www.gstatic.com/firebasejs/7.14.3/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/7.14.3/firebase-analytics.js"></script>
<script src="https://www.gstatic.com/firebasejs/7.14.3/firebase-messaging.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>

<script>
    // Your web app's Firebase configuration
    var firebaseConfig = {
        apiKey: "AIzaSyDFbsRkwvTwZJoFHzmLmb0yUTOEijJ4Tw4",
        authDomain: "akbrnyapp.firebaseapp.com",
        databaseURL: "https://akbrnyapp.firebaseio.com",
        projectId: "akbrnyapp",
        storageBucket: "akbrnyapp.appspot.com",
        messagingSenderId: "744234927044",
        appId: "1:744234927044:web:ec0c2c58672c03ba75da91",
        measurementId: "G-CYLXNJ02WF"
    };
    // Initialize Firebase
    firebase.initializeApp(firebaseConfig);


    const  is_logged =  "{{Auth()->check()}}";
    console.log("loggg: "+is_logged);

    const messaging = firebase.messaging();

    @if(Auth()->check())

    var token_notification =  "{{Auth()->user()->token_notification}}"
    console.log("token notification: "+token_notification);

    requestPermission();


     @endif


    // Retrieve Firebase Messaging object.

    //   messaging.usePublicVapidKey("BB04yoC9e0fTW4rVfYtG8elucjFyUhCHT80X7CNlBo9jxHoOYljQUr-vZySycukpvoth3628RITAnUXbJkJCnAs");
  /*  messaging.requestPermission()
        .then(function() {
            console.log('Notification permission granted.');
            // TODO(developer): Retrieve an Instance ID token for use with FCM.
            if(isTokenSentToServer()) {
                console.log('Token already saved.');
                getRegToken();

            } else
            {
                getRegToken();
            }

        })
        .catch(function(err) {
            console.log('Unable to get permission to notify.', err);
        });

*/




    function getRegToken(argument) {
        messaging.getToken()
            .then(function(currentToken) {
                if (currentToken)
                {
                        @if(Auth()->check())
                    var token_notification =  "{{Auth()->user()->token_notification}}";

                    if(currentToken!=token_notification){
                        saveToken(currentToken);
                        console.log("tokeen: "+currentToken);
                        // setTokenSentToServer(true);
                    }

                    @endif

                }
                else
                {
                    console.log('No Instance ID token available. Request permission to generate one.');
                  //  setTokenSentToServer(false);
                }
            })
            .catch(function(err) {
                console.log('An error occurred while retrieving token. ', err);
             //   setTokenSentToServer(false);
            });
    }

    function setTokenSentToServer(sent) {
        window.localStorage.setItem('sentToServer', sent ? 1 : 0);
    }
    function isTokenSentToServer() {

        return window.localStorage.getItem('sentToServer') == 1;
    }
    function requestPermission() {
        console.log('Requesting permission...');
        // [START request_permission]
        Notification.requestPermission().then((permission) => {
            if (permission === 'granted') {
                console.log('Notification permission granted.');
                @if(Auth()->check())

                getRegToken();

             @endif


                console.log('Notification permission granted.');

            } else {
                console.log('Unable to get permission to notify.');
            }
        });

        // [END request_permission]
    }
    function saveToken(currentToken) {
       // console.log("tokeen seeendd ");
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        var _token = $('input[name="_token"]').val();

        $.ajax({
            url:"{{route('ajax.saveNotificationToken')}}",
            method:"POST",
            //   data:{currentToken:"test to2011"},
            ///   data:{query:"testtt"},
            data:{currentToken:currentToken, _token:_token},
            success:function(data)
            {
               // console.log(data);
                if(data.success)
                {
                    console.log("data has sent ...");
                }
            }
        });

    }

    messaging.onMessage(function(payload) {
        console.log("Message received. ", payload);
        notificationTitle = payload.data.title;
        notificationOptions = {
            body: payload.data.body,
            icon: payload.data.icon,
            image:  payload.data.image
        };
        var notification = new Notification(notificationTitle,notificationOptions);
    });

</script>
<script src="{{asset('style/js/bootstrap.min.js')}}"></script>
<script src="{{asset('style/js/owl.carousel.js')}}"></script>
<script src="{{asset('style/js/plug.js')}}"></script>
<script src="{{asset('style/md/js/ripples.min.js')}}"></script>
<script src="{{asset('style/md/js/material.js')}}" ></script>
<script src="{{asset('style/js/lib/sharer.min.js')}}"></script>
<script src="{{asset('style/app/alertify.min.js')}}"></script>
<script> $.material.init()</script>
<script type="text/javascript">
    $(document).ready(function(){
       //  fetch_search();
        function fetch_search(query = '')
        {
            $.ajax({
                url:"{{ route('ajax.siteSearch')}}",
                method:'GET',
                data:{query:query},
                dataType:'json',
                success:function(data)
                {
                    if(data.success){
                        console.log(''+data.success);
                        $('#search_result').html(data.success);
                    }

                }
            })
        }

        $(document).on('keyup', '#search_input', function(){
            var query = $(this).val();

            if(query!=''){
                //  console.log(''+query);
                fetch_search(query);
            }

        });
    });
</script>
@yield('js')

</div>
</body>
</html>




