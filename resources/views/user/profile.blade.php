@extends('layouts.app')

<?php
$image='';
if($user->image==null){
    $image=  'img/avatar2.png';

}else{

    $image= 'images/profile/'.$user->image;
}
?>

@section('title',$user->name. ' - اخبرني')

@section('twitter_title',$user->name. ' - اخبرني')

@section('twitter_img',''.asset($image))
@section('fb_title',$user->name. ' - اخبرني')
@section('fb_url','http://akbrny/'.$user->username)

@section('content')
    <div class="container">
        <div class="row profile">
            <div class="col-xs-12 col-lg-12 col-md-12">
                <div class="profile-back">

                    <i class="fas fa-ellipsis-v" data-toggle="modal" data-target=".modal-user"></i>

                </div>

                <div class="profile-image center-block text-center">
                    <img src="@if($user->image==null){{asset('img/avatar2.png')}}@else{{asset('images/profile/'.$user->image)}}@endif"  class="img-responsive" alt="{{$user->name}}" />

                </div>

                <div class="profile-info center-block text-center">
                    <h4>{{$user->name}}</h4>
                    <p>{{"@".$user->username}}</p>

                    <div class="profile-social center-block text-center">
                        @if($user->web)
                            <a target="_blank" href="{{$user->web}}"><i class="fas fa-link"></i></a>
                        @endif
                        @if($user->twitter)
                         <a target="_blank" href="{{$user->twitter}}"><i class="fab fa-twitter"></i></a>
                        @endif
                        @if($user->instagram)
                                <a target="_blank" href="{{$user->instagram}}"><i class="fab fa-instagram"></i></a>
                        @endif
                        @if($user->snapchat)
                                <a target="_blank" href="{{$user->snapchat}}"><i class="fab fa-snapchat-ghost"></i></a>
                        @endif
                        @if($user->youtube)
                                <a target="_blank" href="{{$user->youtube}}"><i class="fab fa-youtube"></i></a>
                        @endif

                        @if($user->telegram)
                                <a target="_blank" href="{{$user->telegram}}"><i class="fab fa-telegram"></i></a>
                        @endif

                        @if($user->facebook)
                                <a target="_blank" href="{{$user->facebook}}"><i class="fab fa-facebook-f "></i></a>
                        @endif

                        @if($user->linkedin)
                                <a target="_blank" href="{{$user->linkedin}}/"><i class="fab fa-linkedin-in"></i></a>
                        @endif
                    </div>
                    <div class="row" style="margin-top: 15px">
                        <div class="col-xs-4 col-lg-4 col-md-4">
                            اخبروه<br> <b> {{count($posts)}} </b>
                        </div>
                        <div class="col-xs-4 col-lg-4 col-md-4">
                            الاسئلة<br> <b> 0 </b>
                        </div>
                        <div class="col-xs-4 col-lg-4 col-md-4">
                            التصويت<br> <b> {{count($polls)}} </b>
                        </div>


                    </div>
                </div>

                <div class="profile-message">
                    <p class="text-center">@if($user->text_profile) {{$user->text_profile}} @else  اخبرني عن اي شيء تريده هنا , فلن تظهر هويتك   @endif </p>
                    @include('layouts.alerts')

                    <form method="post" class="text-center center-block" action="{{route('profile.senMessageToUser')}}">
                        @csrf
                        <div class="form-group">
                            <textarea name="message" id="message" class="form-control" rows="4" placeholder=" اكتب النص هنا " required></textarea>

                        </div>
                        <input type="hidden" name="user_id" value="{{$user->id}}"/>
                        <button type="submit" class="btn btn-primary center-block btn-raised"> إرسال <i class="fab fa-telegram-plane"> </i></button>
                    </form>
                    <hr>
                    @if($user->show_zwar)
                        الزيارات : {{$user->visitors}}
                    @endif
                </div>

                <br>
                <div class="profile-messages">
                    <div class="main-content">
                        <div class="line"><div></div></div>
                        <div class="text">   الرسائل والتصويت التي سمح بإظهارها  </div>
                        <div class="line"><div></div></div>
                    </div>

                    @if(count($posts_polls)==0)
                        <p class="text-center">لاتوجد لديك رسائل</p>
                    @else

                        @foreach($posts_polls as $post)

                            @if($post->type==0)
                            <!-- messages -->
                                <div class="panel panel-default  arrow left">
                                    <div class="panel-body">
                                        <header class="text-right">
                                            <div class="text-primary"><i class="fa fa-user"></i> مجهول</div>
                                            <time class="text-primary" datetime="16-12-2014 01:05"><i class="fa fa-clock-o"></i>{{$post->created_at->diffForHumans()}}</time>
                                        </header>
                                        <div class="comment-post text-center">
                                            <p>
                                                {{$post->body}}

                                            </p>
                                        </div>

                                        @if($post->answers->count()==1)
                                            <div class="panel-default arrow left replay">
                                                <div class="panel-heading right"> <li class="fas fa-reply"></li> رد {{$user->name}}   </div>
                                                <div class="panel-body">
                                                    <p>
                                                        {{$post->answers[0]['body']}}
                                                    </p>
                                                </div>
                                            </div>
                                        @endif

                                    </div>
                                </div>


                            @else
                            <!-- polls -->
                                <div class="panel panel-default arrow right polls">
                                    <div class="panel-body">

                                        <div id="vote_result_{{$post->id}}"></div>

                                        <?php
                                        $answer_select=0;

                                        if(auth()->check()) {
                                            $viewerAnswer = ($viewerAnswersByPostId ?? collect())->get($post->id);
                                            if($viewerAnswer != null){
                                                $answer_select = $viewerAnswer->body;
                                            }
                                        }
                                        ?>

                                        <header class="text-right">
                                            <div class="comment-user text-primary"><i class="fa fa-user"></i> {{$user->name}}</div>
                                            <time class="text-primary" datetime="16-12-2014 01:05"><i class="fa fa-clock-o"></i>{{$post->created_at->diffForHumans()}}</time>
                                        </header>
                                        <p>

                                        <h4>{{$post->body}}</h4>
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


                                        @if($answer_select==1)
                                            <li class="fa fa-check"></li>
                                        @endif
                                        <strong>{{$post->answer1}} </strong>
                                        <a href="#" onclick="vote_now('{{$post->id}}',1);">
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar progress-bar-info" role="progressbar"
                                                     style="width: {{percentageOf( $numbers[0], $everything )}}%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">{{percentageOf( $numbers[0], $everything )}}%</div>
                                                <div class="text-left">{{$numbers[0]}}</div>

                                            </div>

                                        </a>

                                        @if($answer_select==2)
                                            <li class="fa fa-check"></li>
                                        @endif
                                        <strong>{{$post->answer2}} </strong>
                                        <a href="#" onclick="vote_now('{{$post->id}}',2);">
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar progress-bar-success" role="progressbar" style="width: {{percentageOf($numbers[1], $everything )}}%;"
                                                     aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">{{percentageOf( $numbers[1], $everything )}}%</div>
                                                <p class="text-left">{{$numbers[1]}}</p>
                                            </div>
                                        </a>


                                        @if($post->answer3 !=null)
                                            @if($answer_select==3)
                                                <li class="fa fa-check"></li>
                                            @endif
                                            <strong>{{$post->answer3}}</strong>
                                            <a href="#" onclick="vote_now('{{$post->id}}',3);">
                                                <div class="progress" style="height:20px;">
                                                    <div class="progress-bar progress-bar-danger" role="progressbar" style="width: {{percentageOf( $numbers[2], $everything )}}%;"
                                                         aria-valuenow="50" aria-valuemin="0" aria-valuemax="100">{{percentageOf( $numbers[2], $everything )}}%</div>

                                                    <p class="text-left">{{$numbers[2]}}</p>

                                                </div>
                                            </a>
                                        @endif


                                        @if($post->answer4 !=null)
                                            @if($answer_select==4)
                                                <li class="fa fa-check"></li>
                                            @endif
                                            <strong>{{$post->answer4}}</strong>
                                            <a href="#" onclick="vote_now('{{$post->id}}',4);">
                                                <div class="progress" style="height:20px;">
                                                    <div class="progress-bar progress-bar-danger" role="progressbar" style="width: {{percentageOf( $numbers[3], $everything )}}%;"
                                                         aria-valuenow="50" aria-valuemin="0" aria-valuemax="100">{{percentageOf( $numbers[3], $everything )}}%</div>
                                                    <p class="text-left">{{$numbers[3]}}</p>
                                                </div>
                                            </a>
                                        @endif
                                        <p class="text-primary"> اجمالي التصويت: <span id="count_vote_{{$post->id}}" data-count="{{$count_all}}">{{$count_all}}</span> </p>
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

@endsection

@section('js')

    <script type="text/javascript">
        //vote
        function vote_now(post_id,select_id){



            var AuthUser = "{{{ (Auth::check()) ? Auth::check() : false }}}";

            alertify.defaults.glossary.title = 'نسخ الرابط ';
            alertify.defaults.glossary.ok = 'نعم';
            alertify.defaults.glossary.cancel = 'إلغاء';
            alertify.defaults.transition = "slide";
            alertify.defaults.theme.ok = "btn btn-primary";
            alertify.defaults.theme.cancel = "btn btn-danger";
            alertify.defaults.theme.input = "form-control";
            console.log(AuthUser);

            if(AuthUser){
                var msg='هل ترغب بالتاكيد التصويت على هذا الخيار؟';


                alertify.confirm('تأكيد ',msg , function(){
                        console.log(''+post_id);
                        $.ajaxSetup({
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            }

                        });

                        $.ajax({
                            url:"{{ route('ajax.profileSendVote') }}",
                            method:"POST",
                            data:{post_id:post_id,select_id:select_id},
                            success:function(result)
                            {

                                if(result.success){

                                    $('#vote_result_'+post_id).html('<div class="alert alert-success">'+result.success+'</div>');

                                    var count = Number($('#count_vote_'+post_id).attr("data-count")) ;

                                    $('#count_vote_'+post_id).html(count+1)


                                }

                                if(result.error){
                                    $('#vote_result_'+post_id).html('<div class="alert alert-danger">'+result.error+'</div>');

                                }


                            }
                        })




                    }
                    , function(){
                        //	alertify.error('الغاء')

                    });



            }
            else
              {
                  var msg='يمكن التصويت فقط للمسجلين بالموقع , هل ترغب بالتسجيل الآن؟';


                  alertify.confirm('لم يتم التصويت ',msg , function(){

                          location.href ='{{route('register')}}';


                      }
                      , function(){
                          //	alertify.error('الغاء')

                      });


            }



        }








    </script>



@endsection
