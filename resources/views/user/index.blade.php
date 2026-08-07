@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row profile">
            <div class="col-xs-12 col-lg-12 col-md-12">
                <div class="profile-back">
                 </div>
                <div class="profile-image center-block text-center">
                    <img id="create_record" src="@if(auth()->user()->image==null){{asset('img/avatar2.png')}}@else{{asset('images/profile/'.auth()->user()->image)}}@endif"  class="img-responsive" alt="{{auth()->user()->name}}" />

                </div>


                <div class="profile-info center-block text-center">
                    <h4>{{auth()->user()->name}}  <a href="{{route('user.settings')}}"><li class="fa fa-cog"></li> </a> </h4>
                    <p> {{config('app.url_site').auth()->user()->username}}</p>

                    <div class="profile-social center-block text-center">

                        <button onclick="copyLink('{{config('app.url_site').  auth()->user()->username}}');" class="btn text-primary"><li class="fa fa-copy"></li> نسخ </button>
                        <button data-toggle="modal" data-target=".modal-share" class="btn btn-primary"><li class="fa fa-share-alt"></li> مشاركة </button>


                    </div>

                    <div class="row" style="margin-top: 15px">
                        <div class="col-xs-4 col-lg-4 col-md-4">
                            اخبروني<br> <b> {{count($posts)}} </b>
                        </div>
                        <div class="col-xs-4 col-lg-4 col-md-4">
                            الاسئلة<br> <b> 0 </b>
                        </div>
                        <div class="col-xs-4 col-lg-4 col-md-4">
                            التصويت<br> <b> {{count($polls)}} </b>
                        </div>




                    </div>
                    الزيارات : {{auth()->user()->visitors}}
                    <br>


                </div>



                <div class="profile-messages">
                    <div class="main-content">
                        <div class="line"><div></div></div>
                        <div class="text">   الرسائل والتصويت </div>
                        <div class="line"><div></div></div>
                    </div>
                    <a href="#" data-toggle="modal" data-target=".modal-poll" class="btn btn-success pull-left"><i class="fa fa-plus"></i>إنشاء تصويت جديد  </a>
                    <br>
                    <br>

                    @if(count($posts_polls)==0)

                        <p class="text-center">لاتوجد لديك رسائل</p>

                    @else


                        @foreach($posts_polls as $post)


                            @if($post->type==0)
                                <!-- messages -->
                                    <div class="panel panel-default  arrow left">

                                        <div class="panel-body">

                                            <header class="text-right">
                                                <div class="comment-user text-primary"><i class="fa fa-user"></i> مجهول</div>
                                                <time class="text-primary" datetime="16-12-2014 01:05"><i class="fa fa-clock-o"></i>{{$post->created_at->diffForHumans()}}</time>
                                            </header>
                                            <div class="comment-post text-center">
                                                <p>
                                                    {{$post->body}}

                                                </p>
                                            </div>

                                            @if($post->answers->count()==1)

                                            <div class="panel-default arrow left replay">
                                                <div class="panel-heading right"> <li class="fas fa-reply"></li> الرد   </div>
                                                <div class="panel-body">
                                                    <p>
                                                        {{$post->answers[0]['body']}}
                                                    </p>
                                                </div>
                                            </div>
                                            @endif

                                            <p class="text-right">
                                                @if($post->answers->count()==1)
                                          <a href="#" onclick="deleteReply('{{$post->answers[0]['id']}}','{{$post->id}}');" class="btn text-primary btn-sm"><i class="fa fa-trash"></i> حذف الرد </a>

                                                @else
                                                    <a href="#" data-toggle="modal" data-target=".modal-reply" onclick="reply2('{{$post->body}}','{{$post->id}}');" class="btn text-primary btn-sm"><i class="fa fa-reply"></i> الرد </a>

                                                @endif


                                                @if($post->is_public==1)
                                                        <a href="#" onclick="editMsg('{{$post->id}}',1,0);"  href="#" class="btn text-primary btn-sm"><i class="fa fa-eye-slash"></i>  </a>

                                                    @else
                                                        <a href="#" onclick="editMsg('{{$post->id}}',0,0);"class="btn text-primary btn-sm"><i class="fa fa-eye"></i>  </a>

                                                    @endif

                                                <a href="#" onclick="deleteMsg('{{$post->id}}')" class="btn text-primary btn-sm"><i class="fa fa-trash"></i>  </a>

                                            </p>
                                        </div>
                                    </div>


                                @else
                                <!-- polls -->
                                    <div class="panel panel-default arrow right polls">
                                        <div class="panel-body">
                                            <header class="text-right">
                                                <div class="text-primary"><i class="fa fa-user"></i> {{auth()->user()->name}} </div>
                                                <time class="text-primary" datetime="16-12-2014 01:05"><i class="fa fa-clock-o"></i>{{$post->created_at->diffForHumans()}}</time>
                                            </header>
                                            <p>

                                            <h3>{{$post->body}}</h3>
                                            <?php

                                            $count_all= $post->answers->count();
                                            $count_answer1= 0;
                                            $count_answer2= 0;
                                            $count_answer3= 0;
                                            $count_answer4= 0;
                                            foreach ($post->answers as $answer)
                                                {
                                                    if($answer->body==1){
                                                     $count_answer1=$count_answer1+1;
                                                    }
                                                    if($answer->body==2){
                                                        $count_answer2=$count_answer2+1;
                                                    }
                                                    if($answer->body==3){
                                                        $count_answer3=$count_answer3+1;
                                                    }
                                                    if($answer->body==4){
                                                        $count_answer4=$count_answer4+1;
                                                    }
                                                }



                                            $numbers = array( $count_answer1, $count_answer2, $count_answer3, $count_answer4 );
                                            $everything = array_sum($numbers);


                                            ?>


                                            <strong>{{$post->answer1}} </strong>
                                            <a href="#">
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar progress-bar-info" role="progressbar"
                                                         style="width: {{percentageOf( $numbers[0], $everything )}}%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">{{percentageOf( $numbers[0], $everything )}}%</div>
                                                    <p class="text-left">{{$numbers[0]}}</p>  </div>
                                            </a>

                                            <strong>{{$post->answer2}} </strong>
                                            <a href="#">
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar progress-bar-success" role="progressbar" style="width: {{percentageOf($numbers[1], $everything )}}%;"
                                                         aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">{{percentageOf( $numbers[1], $everything )}}%</div>
                                                    <p class="text-left">{{$numbers[1]}}</p>
                                                </div>
                                            </a>

                                            @if($post->answer3 !=null)

                                                <strong>{{$post->answer3}}</strong>
                                                <a href="#">
                                                    <div class="progress" style="height:20px;">
                                                        <div class="progress-bar progress-bar-danger" role="progressbar" style="width: {{percentageOf( $numbers[2], $everything )}}%;"
                                                             aria-valuenow="50" aria-valuemin="0" aria-valuemax="100">{{percentageOf( $numbers[2], $everything )}}%</div>


                                                        <p class="text-left">{{$numbers[2]}}</p>

                                                    </div>
                                                </a>
                                                @endif

                                            @if($post->answer4 !=null)

                                                <strong>{{$post->answer4}}</strong>
                                                <a href="#">
                                                    <div class="progress" style="height:20px;">
                                                        <div class="progress-bar progress-bar-danger" role="progressbar" style="width: {{percentageOf( $numbers[3], $everything )}}%;"
                                                             aria-valuenow="50" aria-valuemin="0" aria-valuemax="100">{{percentageOf( $numbers[3], $everything )}}%</div>
                                                        <p class="text-left">{{$numbers[3]}}</p>
                                                    </div>
                                                </a>
                                                @endif
                                            <p> اجمالي التصويت: {{$count_all}}</p>

                                            @if($post->is_active==1)
                                                <a href="#" onclick="editMsg('{{$post->id}}',1,1);"  class="btn btn-primary btn-sm"><i class="fa fa-eye-slash"></i> ايقاف </a>

                                            @else
                                                <a href="#" onclick="editMsg('{{$post->id}}',0,1);" class="btn btn-primary btn-sm"><i class="fa fa-eye"></i> تفعيل </a>
                                            @endif

                                            <a href="#" onclick="deleteMsg('{{$post->id}}')" class="text-primary btn-sm"> <i class="fa fa-trash"></i> </a>


                                            </p>


                                        </div>
                                    </div>

                                @endif


                            @endforeach

                        @endif

                    <br>
                    <br>

                </div>

            </div>
        </div> <!-- End profile -->
    </div>
    <!-- Models -->
    <div class="modal fade modal-user" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel">
        <div class="modal-dialog modal-sm text-center" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close blue" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h3 class="title">خيارات</h3>

                </div>

                <div class="list-group text-center center-block">
                    <button type="button" class="list-group-item btn-block">إبلاغ عن المستخدم</button>
                    <br>

                    <button type="button" class="btn btn-default blue-bg dark blue-bor" data-dismiss="modal">اغلق النافذه <i class="fas fa-times"></i> </button>
                </div>

            </div>
        </div>
    </div>

    <!-- Model replay -->
    <div class="modal fade modal-reply" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel">
        <div class="modal-dialog modal-lg text-center" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close blue" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h3 class="title">الرد على الرسالة</h3>
                </div>

                <div class="panel panel-default  arrow left">
                    <div class="panel-body">
                        <header class="text-right">
                            <div class="comment-user"><i class="fa fa-user"></i> مجهول</div>
                         </header>
                        <div class="comment-post">
                            <p id="message_to_reply">

                            </p>
                        </div>
                        <p class="text-right">




                            <span id="form_result"></span>
                        <form method="post" id="reply_form" >
                            @csrf
                            <div class="form-group">
                                <textarea name="reply" id="reply" class="form-control" rows="4" placeholder="اكتب الرد هنا "></textarea>

                                <p class="help-block">بعد الرد سوف تكون الرسالة مرئية للجميع</p>

                            </div>



                             <div class="form-group" align="center">
                                <input type="hidden" name="action" id="action" />
                                <input type="hidden" name="message_reply_id" id="message_reply_id" value="" />
                                 <button class="btn btn-primary" type="submit">إرسال </button>
                            </div>
                        </form>


                        </p>


                    </div>
                </div>


                <button type="button" class="btn btn-default blue-bg dark blue-bor" data-dismiss="modal">اغلق النافذه <i class="fas fa-times"></i> </button>


            </div>
        </div>
    </div>
    <!-- Model Share -->
    <div class="modal fade modal-share" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel1">
        <div class="modal-dialog text-center" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close blue" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h3 class="title">شارك الرابط</h3>
                </div>

                <div class="panel panel-default  arrow left">
                    <div class="panel-body">

                <button class="btn btn-primary btn-block" style="width: 70%;margin-bottom: 7px" data-sharer="twitter" data-title="{{trans('main.text_share_profile')}}" data-hashtags="اخبرني" data-url="{{config('app.url_site')}}{{auth()->user()->username}}">شارك على تويتر <li class="fab fa-twitter fa-2x"></li> </button>
                <br><button class="btn btn-primary btn-block" style="width: 70%;margin-bottom: 7px" data-sharer="whatsapp" data-title="{{trans('main.text_share_profile')}}" data-hashtags="اخبرني" data-url="{{config('app.url_site')}}{{auth()->user()->username}}" data-web>شارك على واتس اب <li class="fab fa-whatsapp fa-2x"></li> </button>
                 <br> <button class="btn btn-primary btn-block" style="width: 70%;margin-bottom: 7px" data-sharer="telegram" data-title="{{trans('main.text_share_profile')}}" data-hashtags="اخبرني" data-url="{{config('app.url_site')}}{{auth()->user()->username}}">شارك على تليقرام <li class="fab fa-telegram fa-2x"></li> </button>
                   <br> <button class="btn btn-primary btn-block" style="width: 70%;margin-bottom: 7px" data-sharer="facebook" data-title="{{trans('main.text_share_profile')}}" data-hashtag="اخبرني" data-url="{{config('app.url_site')}}{{auth()->user()->username}}">شارك على فيس بوك <li class="fab fa-facebook fa-2x"></li> </button>



                    </div>
                </div>








            </div>
        </div>
    </div>

    <!-- Model Poll -->
    <div class="modal fade modal-poll" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close blue pull-left" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h3 class="title">إنشاء تصويت جديد</h3>
                </div>


                <div class="panel panel-default  arrow left">
                    <div class="panel-body">
                        <header class="text-right">
                        </header>
                        <div class="comment-post">
                        </div>
                        <p class="text-right">
                            <span id="form_question_result"></span>
                        <form id="form_question">

                            <div class="form-group">
                                <label for="question">عنوان التصويت او السؤال</label>
                                <input type="text" name="question" class="form-control" id="question" placeholder="عنوان التصويت او السؤال" required>
                            </div>

                            <div class="form-group">
                                <input type="text" class="form-control" id="option1" name="option1" placeholder="الخيار الاول" required>
                            </div>

                            <div class="form-group">
                                <input type="text" class="form-control" id="option2" name="option2" placeholder="الخيار الثاني" required>
                            </div>

                            <div class="form-group">
                                <input type="text" class="form-control" id="option3" name="option3" placeholder="الخيار الثالث (اختياري)">
                            </div>
                            <div class="form-group">
                                <input type="text" class="form-control" id="option4" name="option4" placeholder="الخيار الرابع (اختياري)">
                            </div>
                            <button type="submit" class="btn btn-primary">إنشاء</button>
                        </form>

                        </p>
                    </div>
                </div>


            </div>
        </div>
    </div>








@endsection

@section('js')




    <script type="text/javascript">

        function copyLink(link=''){
            alertify.defaults.glossary.title = 'نسخ الرابط ';
            alertify.defaults.glossary.ok = 'موافق';
            alertify.defaults.glossary.cancel = 'إلغاء';
            alertify.defaults.transition = "slide";
            alertify.defaults.theme.ok = "btn btn-primary";
            alertify.defaults.theme.cancel = "btn btn-danger";
            alertify.defaults.theme.input = "form-control";
            alertify.alert('قم بالتحديد على الرابط ثم نسخه : <br>  ' +'<b style="text-align: center">  ' +
                ' <input class="form-control" type="text" value="'+link+'" id="myInput">\n </b>' +
                '' +
                '<button  style="margin-top: 6px;" onclick="copyText()" class="btn btn-primary">نسخ  </button> <span id="done_copy"></span>'
                ,function () {
            });
        }


        function copyText() {
            var copyText = document.getElementById("myInput");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");
            $('#done_copy').html('تم النسخ');

        }


        function shareLink(link='swaa'){
            alertify.defaults.glossary.title = 'مشاركة الرابط ';
            alertify.defaults.glossary.ok = 'موافق';
            alertify.defaults.glossary.cancel = 'إلغاء';
            alertify.defaults.transition = "slide";
            alertify.defaults.theme.ok = "btn btn-primary";
            alertify.defaults.theme.cancel = "btn btn-danger";
            alertify.defaults.theme.input = "form-control";
            alertify.alert(text,function () {
            });

        }




        function reply(id=''){
            alertify.defaults.glossary.title = 'نسخ الرابط ';
            alertify.defaults.glossary.ok = 'موافق';
            alertify.defaults.glossary.cancel = 'إلغاء';
            alertify.defaults.transition = "slide";
            alertify.defaults.theme.ok = "btn btn-primary";
            alertify.defaults.theme.cancel = "btn btn-danger";
            alertify.defaults.theme.input = "form-control";

            alertify.alert('قم بالتحديد على الرابط ثم نسخه : <br>  ' +'<b style="text-align: center">'+link+' </b>',function () {

            });



        }



        function deleteMsg(post_id){
            alertify.defaults.glossary.title = 'نسخ الرابط ';
            alertify.defaults.glossary.ok = 'نعم';
            alertify.defaults.glossary.cancel = 'إلغاء';
            alertify.defaults.transition = "slide";
            alertify.defaults.theme.ok = "btn btn-primary";
            alertify.defaults.theme.cancel = "btn btn-danger";
            alertify.defaults.theme.input = "form-control";
            alertify.confirm('تأكيد الحذف', 'هل بالتأكيد تريد حذف هذه الرسالة ؟', function(){
                    console.log(''+post_id);
                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    });
                    $.ajax({
                        url:"{{ route('ajax.delete_message')}}",
                        method:"POST",
                        data:{post_id:post_id},
                        success:function(result)
                        {
                            if(result.success)
                            {
                                var delay = alertify.get('notifier','delay');
                                alertify.set('notifier','delay', 0);
                                alertify.success('{{trans('main.message_deleted_done')}}');
                                alertify.set('notifier','delay', delay);
                                alertify.set('notifier','position', 'top-left');
                            }
                            //console.log(''+result);
                            // console.log(JSON.stringify(result))
                        }
                    });



                }
                , function(){
                    //	alertify.error('الغاء')

                });



        }


        // get all data
        $(document).ready(function(){
            console.log('start get data');

        });



        // reply request
        $('#reply_form').on('submit', function(event){
            event.preventDefault();
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }

            });

            var dataform =new FormData($('#reply_form')[0]);

            console.log(dataform.get('message_reply_id'));

            var id = dataform.get('message_reply_id').trim();
            var reply = dataform.get('reply').trim();

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }

            });
            if(reply === ""){
                $('#form_result').html('<div class="alert alert-danger">{{trans('main.message_please_enter_reply')}}</div>');

            }else{


                $.ajax({
                    url:"{{route('ajax.reply_message')}}",
                    method:"POST",
                    data: new FormData(this),
                    contentType: false,
                    cache:false,
                    processData: false,
                    dataType:"json",
                    success:function(data)
                    {
                        var html = '';

                        // console.log(''+data);

                        if(data.errors)
                        {
                             html = '<div class="alert alert-danger">';
                           // for(var count = 0; count < data.errors.length; count++)
                           // {
                              //  html += '<p>' + data.errors[count] + '</p>';
                           // }
                            html+=data.errors;
                             html += '</div>';

                        }
                        if(data.success)
                        {
                            html = '<div class="alert alert-success">' + data.success + '</div>';
                            $('#reply_form')[0].reset();
                            //$('#user_table').DataTable().ajax.reload();
                        }
                        $('#form_result').html(html);
                    }
                })

            }

        });

        //delete reply
        function deleteReply(reply_id,post_id){
            alertify.defaults.glossary.title = 'نسخ الرابط ';
            alertify.defaults.glossary.ok = 'نعم';
            alertify.defaults.glossary.cancel = 'إلغاء';

            alertify.defaults.transition = "slide";
            alertify.defaults.theme.ok = "btn btn-primary";
            alertify.defaults.theme.cancel = "btn btn-danger";
            alertify.defaults.theme.input = "form-control";

            alertify.confirm('تأكيد الحذف', 'هل بالتأكيد تريد حذف هذه الرد ؟', function(){
                    console.log(''+post_id);

                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }

                    });

                    $.ajax({
                        url:"{{ route('ajax.delete_reply_message')}}",
                        method:"POST",
                        data:{reply_id:reply_id},
                        success:function(result)
                        {
                            if(result.success)
                            {
                                 var delay = alertify.get('notifier','delay');
                                 alertify.set('notifier','delay', 0);
                                 alertify.success('{{trans('main.message_deleted_reply_done')}}');
                                 alertify.set('notifier','delay', delay);
                                 alertify.set('notifier','position', 'top-left');
                            }
                            //console.log(''+result);
                           // console.log(JSON.stringify(result))
                        }
                    });
                }
                , function(){
                    //	alertify.error('الغاء')

                });
        }
        function reply2(text,id){
            // $('#message_to_reply').text('sweilem');
            $('#message_to_reply').html(text);
            $('#message_reply_id').val(id);

        }

        //show and hide message
        function editMsg(post_id,type,is_question){
            alertify.defaults.glossary.title = 'نسخ الرابط ';
            alertify.defaults.glossary.ok = 'نعم';
            alertify.defaults.glossary.cancel = 'إلغاء';
            alertify.defaults.transition = "slide";
            alertify.defaults.theme.ok = "btn btn-primary";
            alertify.defaults.theme.cancel = "btn btn-danger";
            alertify.defaults.theme.input = "form-control";

            var msg='';

            if(is_question==1){

                if(type==1){
                    msg='هل بالتأكيد تريد ايقاف استقبال اصوات جديدة ؟'
                }
                else{
                    msg='هل بالتأكيد تريد فتح استقبال اصوات جديدة ؟'
                }

            }
            else{
                if(type==1){
                    msg='هل بالتأكيد تريد اخفاء هذه الرسالة عن العام ؟'
                }
                else{
                    msg='هل بالتأكيد تريد اظهار هذه الرسالة للعامة ؟'
                }


            }


            alertify.confirm('تأكيد ',msg , function(){
                    console.log(''+post_id);

                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }

                    });

                    $.ajax({
                        url:"{{ route('ajax.edit_message')}}",
                        method:"POST",
                        data:{post_id:post_id,type:type,is_question:is_question},
                        success:function(result)
                        {
                            if(result.success)
                            {
                                var delay = alertify.get('notifier','delay');
                                alertify.set('notifier','delay', 0);
                                alertify.success(result.success);
                                alertify.set('notifier','delay', delay);
                                alertify.set('notifier','position', 'top-left');
                            }
                            //console.log(''+result);
                            // console.log(JSON.stringify(result))
                        }
                    });
                }
                , function(){
                    //	alertify.error('الغاء')

                });
        }


        $('#form_question').on('submit', function(event){
            event.preventDefault();
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }

            });

            var dataform =new FormData($('#form_question')[0]);


            var question = dataform.get('question').trim();
            var option1 = dataform.get('option1').trim();
            var option2 = dataform.get('option2').trim();

            console.log(dataform.get('question'));

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }

            });
            if(question === "" || option1 === "" ||  option2 === ""){
                $('#form_question_result').html('<div class="alert alert-danger">{{trans('main.enter_filed_required')}}</div>');

            }
            else{
                $.ajax({
                    url:"{{route('ajax.question_add')}}",
                    method:"POST",
                    data: dataform,
                    contentType: false,
                    cache:false,
                    processData: false,
                    dataType:"json",
                    success:function(data)
                    {
                        var html = '';

                        if(data.errors)
                        {
                            html = '<div class="alert alert-danger">';
                            // for(var count = 0; count < data.errors.length; count++)
                            // {
                            //  html += '<p>' + data.errors[count] + '</p>';
                            // }
                            html+=data.errors;
                            html += '</div>';
                        }
                        if(data.success)
                        {

                            html = '<div class="alert alert-success">' + data.success + '</div>';
                            $('#form_question')[0].reset();
                            //$('#user_table').DataTable().ajax.reload();
                        }


                        $('#form_question_result').html(html);
                    }
                })

            }

        });




    </script>




@endsection
