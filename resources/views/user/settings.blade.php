@extends('layouts.app')

@section('content')


        <div class="container">
            <div class="row">
                <div class="col-xs-12 col-lg-12 col-md-12">

                    <div class="modal-dialog modal-lg profile-setting">
                        <div class="modal-content">
                            <div class="modal-header text-center">
                                <h4 class="modal-title " id="myModalLabel">
                                    تعديل ملفي
                                </h4>
                            </div>
                            <div class="modal-body ">
                                <!-- Nav tabs -->
                                <ul class="nav nav-tabs">
                                    <li class="active"><a href="#tab_information" data-toggle="tab">المعلومات</a></li>
                                    <li><a href="#passwordChange" data-toggle="tab">كلمة المرور</a></li>
                                    <li><a href="#tab_image" data-toggle="tab">الصورة</a></li>
                                    <li><a href="#tab_settings" data-toggle="tab">إعدادات</a></li>
                                    <li><a href="#tab_social" data-toggle="tab">الشبكات الاجتماعية</a></li>
                                </ul>


                                <!-- Tab panes -->
                                <div class="tab-content">
                                    <div class="tab-pane active" id="tab_information" >
                                        <span id="form_edit_info_result"></span>



                                        <form method="post" id="form_edit_info" >
                                            @csrf
                                            <div class="form-group">
                                                <label for="name">الاسم</label>
                                                <input  type="text" value="{{auth()->user()->name}}" class="form-control" id="name" name="name" placeholder="الاسم" required="required" >
                                            </div>

                                            <div class="form-group" id="group-email">
                                                <label for="email">البريد الالكتروني</label>
                                                <input type="email" class="form-control " value="{{auth()->user()->email}}"  id="email" name="email" placeholder="البريد الالكتروني"   value="{{ old('email') }}"   autocomplete="email">
                                            </div>

                                            <div class="form-group">
                                                <label for="text_profile">رسالة الملف الشخصي</label>
                                                <textarea class="form-control "  id="text_profile" name="text_profile" placeholder="رسالة الملف الشخصي">{{auth()->user()->text_profile}}</textarea>
                                            </div>

                                            <div class="form-group">
                                                  <div class="form-group">
                                                    <button type="submit" align="center" class="btn btn-success center-block ajs-center">تعديل البيانات</button>


                                                      <a  class="btn text-danger pull-left" href="{{ route('logout') }}"
                                                         onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                                          تسجيل الخروج
                                                      </a>

                                                </div>
                                            </div>



                                        </form>

                                        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                            @csrf
                                        </form>

                                    </div>

                                    <div class="tab-pane" id="passwordChange">
                                        <span id="form_edit_password_result"></span>

                                        <form method="post" id="form_edit_password" >
                                            @csrf
                                        <div class="form-group pass_show">
                                            <label for="current-password" > كلمة المرور الحالية  </label>
                                             <span class="pull-left text-primary" id="password_val"></span>
                                            <input type="password" class="form-control pass_show" id="current-password" name="current-password" placeholder="كلمة المرور الحالية" required="required">
                                        </div>

                                        <div class="form-group " id="group-password">
                                            <label for="password"> كلمة المرور الجديدة</label>
                                             <input type="password" class="form-control" id="password" name="password" placeholder="كلمة المرور الجديدة" required="required">

                                        </div>
                                        <div class="form-group" id="group-password">
                                            <label for="password-confirm">تأكيد كلمة المرور الجديدة</label>
                                             <input type="password" class="form-control" id="password-confirm" name="password_confirmation" placeholder="تأكيد كلمة المرور الجديدة" required="required">
                                        </div>
                                        <div class="form-group" align="center">
                                            <div class="form-group">
                                                <button type="submit" class="btn btn-success center-block">تغيير كلمة المرور</button>
                                            </div>
                                        </div>
                                        </form>
                                        <br>

                                    </div>

                                    <div class="tab-pane  text-center" id="tab_image">
                                        <span id="form_change_image_result"></span>
                                        <form action="" method="post">
                                            <div class="preview">
                                                <img class="preview-img " id="preview-img" src="@if(auth()->user()->image==null){{asset('img/avatar2.png')}}@else{{asset('images/profile/'.auth()->user()->image)}}@endif" alt="Preview Image" width="150" height="150"/>
                                                <div class="browse-button">
                                                    <i class="fa fa-pencil-alt"></i>
                                                    <input class="browse-input" type="file" required name="UploadedFile" id="upload_image"/>
                                                </div>
                                                <span class="error"></span>
                                            </div>
                                            <p>قم بالضغط على الصورة لتغييرها</p>
                                        </form>
                                    </div>


                                    <div class="tab-pane" id="tab_settings">
                                        <div id="form_settings_result"></div>

                                       <form method="post" id="form_settings">

                                           <h4>اعدادات عامة</h4>


                                           <div class="form-group" id="email22">
                                               <label class="switch pull-left" for="active_notification">
                                                   <input type="checkbox" name="active_notification" id="active_notification" {{auth()->user()->active_notification?'checked="checked"':''}} />
                                                   <div class="slider round"></div>
                                               </label>
                                               <p>تفعيل الاشعارات</p>
                                           </div>

                                           <hr/>

                                           <h4>اعدادات الخصوصية</h4>

                                           <div class="form-group" id="setting1">
                                               <label class="switch pull-left" for="checkbox2">
                                                   <input name="is_public" type="checkbox" id="checkbox2" {{auth()->user()->is_public?'checked="checked"':''}} />
                                                   <div class="slider round"></div>
                                               </label>
                                               <p>حساب عام ؟ (يظهر بالبحث)</p>
                                           </div>

                                           <br>
                                           <div class="form-group" id="setting2">
                                               <label class="switch pull-left" for="checkbox3">
                                                   <input type="checkbox" name="accept_posts" id="checkbox3" {{auth()->user()->accept_posts?'checked="checked"':''}} />
                                                   <div class="slider round"></div>
                                               </label>
                                               <p>استقبال الرسائل من اي شخص</p>

                                           </div>


                                           <br>
                                           <div class="form-group" id="setting3">
                                               <label class="switch pull-left" for="checkbox4">
                                                   <input type="checkbox" name="show_zwar" id="checkbox4" {{auth()->user()->show_zwar?'checked="checked"':''}} />
                                                   <div class="slider round"></div>
                                               </label>
                                               <p>اظهار عدد الزوار بملفي</p>
                                           </div>
                                           <div class="form-group" align="center">
                                                <div class="form-group">
                                                    <button type="submit" class="btn btn-success center-block">حفظ الاعدادات</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="tab-pane" id="tab_social" >
                                        <span id="form_edit_social_result"></span>
                                        <form method="post" id="form_edit_social" >
                                            @csrf

                                            <div class="form-group">
                                                <label for="url" >الموقع الالكتروني </label>
                                                 <input style="direction: ltr;" type="url" value="{{auth()->user()->web}}" type="url" class="form-control" id="web" name="web" >
                                            </div>

                                            <div class="form-group">
                                                <label for="twitter" >رابط حساب تويتر </label>
                                                 <input style="direction: ltr;" type="url"  value="{{auth()->user()->twitter}}"  class="form-control" id="twitter" name="twitter"  >
                                            </div>

                                            <div class="form-group">
                                                <label for="instagram" >رابط حساب انستقرام </label>
                                                 <input style="direction: ltr;" type="url"  name="instagram" id="instagram" value="{{auth()->user()->instagram}}"    class="form-control" >
                                            </div>


                                            <div class="form-group">
                                                <label for="youtube" >رابط حساب يوتيوب </label>
                                                 <input style="direction: ltr;" type="url"  name="youtube" id="youtube"  value="{{auth()->user()->youtube}}" class="form-control" >
                                            </div>


                                            <div class="form-group">
                                                <label for="snapchat" >رابط حساب سناب شات </label>
                                                 <input style="direction: ltr;" type="url"  name="snapchat" id="snapchat"   value="{{auth()->user()->snapchat}}" class="form-control" >
                                            </div>

                                            <div class="form-group">
                                                <label for="telegram" >رابط حساب تليقرام </label>
                                                 <input style="direction: ltr;" type="url"  name="telegram" id="telegram"  value="{{auth()->user()->telegram}}" class="form-control" >
                                            </div>

                                            <div class="form-group">
                                                <label for="facebook" >رابط حساب فيس بوك </label>
                                                 <input style="direction: ltr;" type="url"  name="facebook" id="facebook" value="{{auth()->user()->facebook}}" class="form-control" >
                                            </div>

                                            <div class="form-group">
                                                <label for="linkedin" >رابط حساب Linkedin </label>
                                                 <input style="direction: ltr;" type="url"  name="linkedin" id="linkedin" value="{{auth()->user()->linkedin}}" class="form-control" >
                                            </div>



                                            <div class="form-group" align="center">
                                                <div class="form-group">
                                                    <button type="submit" class="btn btn-success center-block">حفظ التعديلات</button>
                                                </div>
                                            </div>
                                        </form>
                                        <br>



                                    </div>

                                    </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div> <!-- End Edit profile -->
        </div>
        <!-- Upload Image-->
        <div id="uploadimageModal" class="modal" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal"> &times; </button>
                        <h4 class="modal-title">قص وتغيير الصورة </h4>
                    </div>
                    <div class="modal-body center-block text-center">
                             <div class="text-center center-block">
                                <div id="image_demo" style="margin-top:7px"></div>
                            </div>
                                 <br />

                        <div class="form-group">
                            <button  class="btn btn-success crop_image center-block">تغيير الصورة</button>
                        </div>
                     </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">الغاء</button>
                    </div>
                </div>
            </div>
        </div>

            @endsection

 @section('js')
     <script type="text/javascript" src="{{asset('style/app/croppie.js')}}" > </script>
     <link rel="stylesheet" href="{{asset('style/app/css/croppie.css')}}" />

 <script type="text/javascript">
     // edit settings


     $('#form_settings').on('submit', function(event){
         event.preventDefault();

         var check =$('#active_notification:checked').val()?1:0;

         if(check==1){
             requestPermission();
         }

         console.log('check: '+check);


         $.ajaxSetup({
             headers: {
                 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
             }
         });

         var dataform =new FormData($('#form_settings')[0]);
             $.ajax({
                 url: "{{route('ajax.userEditSettings')}}",
                 method: "POST",
                 data: dataform,
                 contentType: false,
                 cache: false,
                 processData: false,
                 dataType: "json",
                 success: function (data) {
                     var html = '';

                     if (data.errors) {
                         html = '<div class="alert alert-danger">';
                         html += data.errors;
                         html += '</div>';
                     }
                     if (data.success) {
                         html = '<div class="alert alert-success">' + data.success + '</div>';
                     }
                     console.log(data.success);
                     $('#form_settings_result').html(html);
                 }
             });

      });



     // edit  form_edit_social
     $('#form_edit_social').on('submit', function(event){
         event.preventDefault();
         $.ajaxSetup({
             headers: {
                 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
             }
         });

         var dataform =new FormData($('#form_edit_social')[0]);
             $.ajax({
                 url: "{{route('ajax.userEditSocial')}}",
                 method: "POST",
                 data: dataform,
                 contentType: false,
                 cache: false,
                 processData: false,
                 dataType: "json",
                 success: function (data) {
                     var html = '';

                     if (data.errors) {
                         html = '<div class="alert alert-danger">';
                         html += data.errors;
                         html += '</div>';
                     }
                     if (data.success) {

                         html = '<div class="alert alert-success">' + data.success + '</div>';
                         // $('#form_question')[0].reset();
                         //$('#user_table').DataTable().ajax.reload();
                     }
                     console.log(data.success);


                     $('#form_edit_social_result').html(html);
                 }
             });

      });


     // edit settings form
        $('#form_edit_info').on('submit', function(event){
        event.preventDefault();
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            var dataform =new FormData($('#form_edit_info')[0]);
            var name = dataform.get('name').trim();
            var email = dataform.get('email').trim();

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }

            });
            if(name === "" || email === ""){
                $('#form_edit_info_result').html('<div class="alert alert-danger">{{trans('main.enter_filed_required')}}</div>');

            }
            else {
                $.ajax({
                    url: "{{route('ajax.userEditInfo')}}",
                    method: "POST",
                    data: dataform,
                    contentType: false,
                    cache: false,
                    processData: false,
                    dataType: "json",
                    success: function (data) {
                        var html = '';

                        if (data.errors) {
                            html = '<div class="alert alert-danger">';
                            html += data.errors;
                            html += '</div>';
                        }
                        if (data.success) {

                            html = '<div class="alert alert-success">' + data.success + '</div>';
                            // $('#form_question')[0].reset();
                            //$('#user_table').DataTable().ajax.reload();
                        }


                        $('#form_edit_info_result').html(html);
                    }
                });

            }
        });

        // edit password
        $('#form_edit_password').on('submit', function(event){
        event.preventDefault();
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }

            });
            var dataform =new FormData($('#form_edit_password')[0]);

            var password = dataform.get('password').trim();
            var current_password = dataform.get('current-password').trim();
            var password_confirmation = dataform.get('password_confirmation').trim();

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }

            });
            if(password === "" || current_password === "" || password_confirmation === ""){
                $('#form_edit_password_result').html('<div class="alert alert-danger">{{trans('main.enter_filed_required')}}</div>');

            }
            else {
                $.ajax({
                    url: "{{route('ajax.userChangePassword')}}",
                    method: "POST",
                    data: dataform,
                    contentType: false,
                    cache: false,
                    processData: false,
                    dataType: "json",
                    success: function (data) {
                        var html = '';

                        if (data.errors) {
                            html = '<div class="alert alert-danger">';
                            html += data.errors;
                            html += '</div>';
                        }
                        if (data.success) {
                            html = '<div class="alert alert-success">' + data.success + '</div>';
                        }


                        $('#form_edit_password_result').html(html);
                    }
                });

            }
        });

        //upload image
        $(document).ready(function(){
            $image_crop = $('#image_demo').croppie({
                enableExif: true,
                viewport: {
                    width:200,
                    height:200,
                    type:'circle' //circle
                },
                boundary:{
                    width:300,
                    height:300
                }
            });

            $('#upload_image').on('change', function(){

                var url2 = this.value;
                var ext = url2.substring(url2.lastIndexOf('.') + 1).toLowerCase();


                if (this.files && this.files[0]&& (ext == "gif" || ext == "png" || ext == "jpeg"
                    || ext == "jpg")) {
                    // here your code
                    var reader = new FileReader();
                    reader.onload = function (event) {

                        $image_crop.croppie('bind', {
                            url: event.target.result

                        }).then(function(){
                            console.log('jQuery bind complete');
                        });
                    }
                    reader.readAsDataURL(this.files[0]);
                      $('#uploadimageModal').modal('show');
                    //files[0]['type']

                    console.log('extt is: '+ext);

                }else
                {
                    console.log('extt is: not image');

                    $('#form_change_image_result').html('<div class="alert alert-danger">الرجاء اختيار صورة صحيحة</div>');
                    //  $('.preview-img').attr('src', 'https://c.disquscdn.com/uploads/users/15284/2796/avatar92.jpg?1519214457');
                }

            });



            $('.crop_image').click(function(event){
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }

                });

                $image_crop.croppie('result', {
                    type: 'canvas',
                    size: 'viewport'
                }).then(function(response){
                    $('.preview-img').attr('src',response);
                     console.log(response);


                    $.ajax({
                        url: "{{route('ajax.userChangeImage')}}",
                        type: "POST",
                        data:{"image": response},
                        success:function(data)
                        {

                            var html = '';

                            if (data.error) {
                                html = '<div class="alert alert-danger">';
                                html += data.error;
                                html += '</div>';
                            }
                            if (data.success) {

                                html = '<div class="alert alert-success">' + data.success + '</div>';
                                // $('#form_question')[0].reset();
                                //$('#user_table').DataTable().ajax.reload();
                            }

                            $('#form_change_image_result').html(html);

                           // console.log(' '+JSON.stringify(data));

                              $('#uploadimageModal').modal('hide');
                           // $('#uploaded_image').html(data);
                        }
                    });






                })
            });

        });





        </script>


@endsection




