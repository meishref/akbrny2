@extends('layouts.app')

@section('content')

    <div class="container">
        <div class="row">
            <div class="col-xs-12 col-lg-12 col-md-12">

                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title text-center" id="myModalLabel">
                                إنشاء حساب جديد

                            </h4>
                        </div>
                        <div class="modal-body model-register">
                            @include('layouts.alerts')

                            <form method="POST" action="{{ route('register') }}">
                                @csrf
                                <div class="form-group">
                                    <label for="name">الاسم</label>
                                    <i class="fa fa-user"></i>
                                    <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus placeholder="الاسم" required="required">

                           </div>


                                <div class="form-group has-feedback" id="group-username">
                                    <label for="username">اسم المستخدم (الرابط الخاص بك) </label>
                                    <i class="fa fa-user-friends"></i>
                                    <input type="text" class="form-control" id="username" value="{{ old('username') }}"  name="username" placeholder="اسم المستخدم" required="required">


                                    <span class="invalid-feedback" role="alert" id="error_username">
                                     </span>

                                    <p class="help-block text-right">اسم المستخدم باللغة الانجليزية وحروف وارقام فقط .</p>
                                    <p class="help-block text-left">http://akbrny.com/<span style="color: #a94442;font-weight: bold" id="text_username"></span></p>


                                </div>

                                <div class="form-group has-feedback" id="group-email">
                                    <label for="email">البريد الالكتروني</label>
                                    <i class="fa fa-envelope"></i>
                                    <input type="email" class="form-control "   id="email" name="email" placeholder="البريد الالكتروني" required="required" value="{{ old('email') }}" required autocomplete="email">


                                    <span class="invalid-feedback" role="alert">
                                        <span id="error_email"></span>
                                    </span>

                                </div>

                                <div class="form-group pass_show">
                                    <label for="password" > كلمة المرور  </label>
                                    <i class="fa fa-key"></i>
                                    <span class="pull-left text-primary" id="password_val"></span>
                                    <input type="password" class="form-control pass_show" id="password" name="password" placeholder="كلمة المرور" required="required">

                                </div>


                                <div class="form-group has-feedback" id="group-password">
                                    <label for="password-confirm">تأكيد كلمة المرور</label>
                                    <i class="fa fa-lock"></i>
                                    <input type="password" class="form-control" id="password-confirm" name="password_confirmation" placeholder="كلمة المرور" required="required">

                                    <span class="invalid-feedback" role="alert">
                                        <span id="error_password"></span>
                                    </span>


                                </div>



                                <div class="form-group">
                                    <button id="register" type="submit" class="btn btn-success btn-block center-block">إنشاء الحساب</button>
                                </div>


                            </form>


                            هل لديك حساب ؟ <a href="{{route('login')}}">تسجيل الدخول</a>

                            <hr>

                            بالضغط على تسجيل فانت توافق على <a href="{{route('pages.terms')}}">شروط الاستخدام</a> و <a href="{{route('pages.privacy-policy')}}">سياسة الخصوصية</a>






                        </div>
                    </div>
                </div>










            </div>
        </div> <!-- End profile -->
    </div>



@endsection

@section('js')
    <script type="text/javascript">
        // register
        $('#username').keyup(function () {
            var val = $(this).val();
            $('#text_username').html(val);
        });
        $(document).ready(function(){
            $('#password_val').append('<span class="ptxt">اظهار</span>');
        });

        $(document).on('click','#password_val', function(){
            $(this).text($(this).text() == "اظهار" ? "اخفاء" : "اظهار");
            $('#password').attr('type', function(index, attr){return attr == 'password' ? 'text' : 'password'; });
            $('#password-confirm').attr('type', function(index, attr){return attr == 'password' ? 'text' : 'password'; });

        });


        // check email
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }

        });

        $(document).ready(function(){
            $('#email').blur(function(){
                var error_email = '';
                var email = $('#email').val();
                var _token = $('input[name="_token"]').val();

                var filter = /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;
                if(!filter.test(email))
                {
                    $('#error_email').html('<label class="text-danger"> البريد الالكتروني غير صحيح</label>');
                    $('#group-email').addClass('has-error');
                    $('#register').attr('disabled', 'disabled');
                }
                else
                {
                    $.ajax({
                        url:"{{ route('email_available.check') }}",
                        method:"POST",
                        data:{email:email, _token:_token},
                        success:function(result)
                        {
                            if(result == 'unique')
                            {
                                $('#error_email').html('<label class="text-success">متوفر</label>');
                                $('#email').removeClass('has-error');
                                $('#register').attr('disabled', false);
                                $('#group-email').addClass('has-success');

                            }
                            else
                            {
                                $('#error_email').html('<label class="text-danger">البريد الالكتروني تم استخدامه من قبل</label>');
                                $('#email').addClass('has-error');
                                $('#group-email').addClass('has-error');
                                $('#register').attr('disabled', 'disabled');
                            }
                        }
                    })
                }
            });

        });

        // check username
        $(document).ready(function(){
            $('#username').blur(function(){
                var error_email = '';
                var username = $('#username').val();
                var _token = $('input[name="_token"]').val();


                var filter = /^([a-zA-Z0-9_\.\-])+$/;
                if(!filter.test(username))
                {
                    $('#error_username').html('<label class="text-danger"> اسم المستخدم غير صحيح</label>');
                    $('#group-username').addClass('has-error');
                    $('#register').attr('disabled', 'disabled');
                }

                 else if(username.length<3)
                {
                    $('#error_username').html('<label class="text-danger"> اسم المستخدم قصير جدا</label>');
                    $('#group-username').addClass('has-error');
                    $('#register').attr('disabled', 'disabled');
                }



                else
                {
                    $.ajax({
                        url:"{{ route('email_available.check') }}",
                        method:"POST",
                        data:{username:username, _token:_token},
                        success:function(result)
                        {
                            if(result == 'unique')
                            {
                                $('#error_username').html('<label class="text-success">متوفر</label>');
                                $('#username').removeClass('has-error');
                                $('#register').attr('disabled', false);
                                $('#group-username').addClass('has-success');

                            }
                            else
                            {
                                $('#error_username').html('<label class="text-danger">اسم المستخدم تم اخذه من قبل</label>');
                                $('#username').addClass('has-error');
                                $('#group-username').addClass('has-error');
                                $('#register').attr('disabled', 'disabled');
                            }
                        }
                    })
                }
            });

        });


        // check password
        $(document).ready(function(){
            $('#password-confirm').blur(function(){
                 var password = $('#password').val();

                 var password_confirm = $('#password-confirm').val();

                if(password.length<6)
                {
                    $('#error_password').html('<label class="text-danger"> كلمة المرور يجب ان تكون اكبر من 5 احرف او ارقام</label>');
                    $('#group-password').addClass('has-error');
                    $('#register').attr('disabled', 'disabled');
                }
                else if(password != password_confirm)
                {
                    $('#error_password').html('<label class="text-danger"> كلمات المرور غير متطابقة</label>');
                    $('#group-password').addClass('has-error');
                    $('#register').attr('disabled', 'disabled');
                }

                else
                {
                    $('#error_password').html('<label class="text-danger">  </label>');
                    $('#group-password').addClass('has-success');
                    $('#register').attr('disabled', false);
                }
            });

        });





    </script>

 @endsection
