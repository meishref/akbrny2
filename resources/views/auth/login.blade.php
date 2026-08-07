@extends('layouts.app')

@section('content')





        <div class="container">
            <div class="row">
                <div class="col-xs-12 col-lg-12 col-md-12">

                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="modal-title text-center" id="myModalLabel">
                                   تسجيل الدخول

                                </h4>
                            </div>
                            <div class="modal-body model-register">
                                @include('layouts.alerts')

                                <form method="POST" action="{{ route('login') }}">
                                    @csrf

                                    <div class="form-group has-feedback" id="group-email">
                                        <label for="email">اسم المستخدم او البريد الالكتروني</label>
                                        <i class="fa fa-envelope"></i>
                                        <input type="text" class="form-control" id="email" name="email" placeholder="البريد الالكتروني" required="required" value="{{ old('email') }}" required autocomplete="email">


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



                                    <div class="form-group">
                                        <button id="register" type="submit" class="btn btn-success btn-block center-block">تسجيل الدخول</button>
                                    </div>


                                </form>


                                ليس لديك حساب ؟ <a href="{{route('register')}}">إنشاء حساب جديد</a>
                                <br>
                                هل نسيت كلمة المرور ؟ <a href="{{route('password.request')}}">استعادة كلمة المرور</a>


                                <hr>



                            </div>
                        </div>
                    </div>










                </div>
            </div> <!-- End profile -->
        </div>














<br>
<br>
<br>



@endsection
