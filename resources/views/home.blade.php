@extends('layouts.app')

@section('content')






    <div class="container-fluid home">
        <div class="row ">
            <div class="col-xs-12 col-lg-12 col-md-12">
                <div class="text-center">
                    <h1>اخبرني</h1>
                    <hr class="hr-light my-4 w-55">
                    <p>هو تطبيق لاستقبال الرسائل المجهولة و الآراء الصادقة من المتابعين والمعجبين على شبكات التواصل الاجتماعي حيث يمكنك التطبيق من التسجيل ونشر رابطك الخاص و استقبال الآراء من متابعيك ومعجبيك وطرح اراءهم عليك ومعرفة نظرتهم لك .</p>

                    <br>

                    @guest()


                        <a href="{{route('login')}}"> <button class="btn btn-raised btn-default"> <li class="fa fa-sign-in-alt"></li> تسجيل الدخول </button> </a>

                    @else

                        <a href="{{route('user.index')}}"> <button class="btn btn-raised btn-primary btn-lg"> <li class="fa fa-user-alt"></li> حسـابي </button> </a>

                        <div>

                        @endguest

                        </div>

            </div>
        </div> <!-- End Home -->
    </div>
    </div>

    <div class="container-fluid home2  text-center">
        <div class="row ">
            <div class="col-xs-12 col-lg-12 col-md-12">

                <h1>لماذا اخبرني ؟</h1>
                <hr class="hr-light my-4 w-55">
                <br>
                <div class="container">
                    <div class="row">
                        <div class="col-md-3 padding">
                            <i class="fa fa-mail-bulk"></i>
                            <br>
                            <b>   استقبال الرسائل المجهولة</b>
                            <p> يمكنك من خلال تطبيق اخبرني استقبال الرسائل من الاشخاص دون معرفة هوية المرسل .</p>
                        </div>

                        <div class="col-md-3 padding">
                            <i class="fa fa-eye-slash"></i>
                            <br>
                            <b>    اظهار الاراء واخفائها</b>
                            <p>يمكنك اظهار الردود التي تحصل عليها من الزوار او اخفائها متى ما اردت .</p>

                        </div>
                        <div class="col-md-3 padding">
                            <i class="fa fa-reply-all"></i>
                            <br>
                            <b>   الرد على الرسائل</b>
                            <p>يمكنك الرد على الرسائل والاراء دون معرفة المرسل واظهارها بصفحتك.</p>

                        </div>
                        <div class="col-md-3 padding">
                            <i class="fa fa-link"></i>
                            <br>
                            <b>   رابطك الخاص </b>
                            <p>يمكنك انشاء رابط خاص بصفحتك او حسابك ونشره بين اصدقائك او متابعينك بسهولة.</p>
                        </div>

                    </div>
                </div>


            </div>
        </div> <!-- End Home2 -->
    </div>
    <div class="container-fluid home2  text-center color-green">
        <div class="row">
            <div class="col-xs-12 col-lg-12 col-md-12">

                <h1>كيف تستخدم الموقع ؟</h1>
                <hr class="hr-light">
                <br>
                <div class="container">
                    <div class="row">
                        <div class="col-md-3 col-lg-3 padding">
                            <i class="fa fa-user-friends"></i>
                            <br>
                            <b>  انشيئ حسابك  </b>
                            <p>قم بالتسجيل وانشاء حسابك من خلال صفحة انشاء حساب من <a href="{{route('register')}}">هنا</a></p>
                        </div>

                        <div class="col-md-3 col-lg-3 padding">
                            <i class="fa fa-link"></i>
                            <br>
                            <b> احصل على رابطك الخاص </b>
                            <p>بعد انشاء حسابك والدخول على صفحتك سوف تجد رابطك الخاص باعلى الصفحة . </p>

                        </div>
                        <div class="col-md-3 col-lg-3 padding">
                            <i class="fa fa-share-alt"></i>
                            <br>
                            <b>    انشر رابطك الخاص  </b>
                            <p>بعد الحصول على رابطك الخاص قم بنشره لاصدقائك او متابعينك ليخبرونك بأرائهم وارسال الرسائل لك.</p>

                        </div>
                        <div class="col-md-3 col-lg-3 padding">
                            <i class="fa fa-envelope-open"></i>
                            <br>
                            <b>    استقبال الرسائل </b>
                            <p>بعد نشر رابطك سوف تجد جميع رسائلك والاراء بصفحتك بالرسائل ويمكنك الرد واظهارها او اخفائها والتفاعل معها .</p>

                        </div>

                    </div>
                </div>


            </div>
        </div> <!-- End Home3 -->
    </div>


    <div class="container-fluid home2">
        <div class="row ">
            <div class="col-xs-12 col-lg-12 col-md-12 text-center">
                <h1 class="text-center">احصل على التطبيق </h1>
                <hr>
                <p>
                    لتحميل التطبيق على الاجهزة التالية قم بالضغط على ايقونة المتجر
                </p>
                <br>
                <div class="col-md-6 col-lg-6 padding text-center">
                    <br>
                    <b>   Android  </b>
                    <p>
                        <a href=""> <img src="{{asset('img/google-play-badge.png')}}" class="img-responsive" ></a>
                    </p>
                </div>
                <div class="col-md-6 col-lg-6 padding text-center">
                    <br>
                    <b>   Iphone OS  </b>
                    <p>
                        قريبا ...

                    </p>

                </div>


            </div>



        </div>

    </div>
    </div> <!-- End Home 4 -->
    </div>
    <br>
    <br>

@endsection
