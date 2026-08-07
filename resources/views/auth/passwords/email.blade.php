@extends('layouts.app')

    @section('content')





        <div class="container">
            <div class="row">
                <div class="col-xs-12 col-lg-12 col-md-12">

                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="modal-title text-center" id="myModalLabel">
                                    استعادة كلمة السر

                                </h4>
                            </div>
                            <div class="modal-body model-register">
                                @include('layouts.alerts')

                                <form method="POST" action="{{ route('password.email') }}">
                                    @csrf

                                    <div class="form-group has-feedback" id="group-email">
                                        <label for="email">البريد الالكتروني</label>
                                        <i class="fa fa-envelope"></i>
                                        <input type="email" class="form-control "   id="email" name="email" placeholder="البريد الالكتروني" required="required" value="{{ old('email') }}" autofocus required autocomplete="email">

                                        <p class="help-block ">ادخل بريدك الالكتروني لاستعادة كلمة المرور</p>

                                        <span class="invalid-feedback" role="alert">
                                        <span id="error_email"></span>
                                    </span>

                                    </div>


                                    <div class="form-group">
                                        <button id="register" type="submit" class="btn btn-success btn-block center-block">استعادة كلمة المرور</button>
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


















@endsection
