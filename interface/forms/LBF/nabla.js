(function(){
    document.addEventListener('DOMContentLoaded', function(){
        var btn = document.getElementById('nabla-record-btn');
        if(!btn){return;}
        var recorder; var chunks=[]; var stream;
        async function start(){
            stream = await navigator.mediaDevices.getUserMedia({audio:true});
            recorder = new MediaRecorder(stream);
            recorder.ondataavailable = e => chunks.push(e.data);
            recorder.onstop = upload;
            recorder.start();
            btn.textContent = 'Stop Nabla Recording';
            btn.classList.add('btn-danger');
        }
        function stop(){
            recorder.stop();
            stream.getTracks().forEach(t=>t.stop());
            recorder = null;
            btn.textContent = 'Start Nabla Recording';
            btn.classList.remove('btn-danger');
        }
        async function upload(){
            var blob = new Blob(chunks,{type:'audio/webm'});
            chunks=[];
            var fd = new FormData();
            fd.append('audio', blob, 'recording.webm');
            fd.append('pid', window.nablaPid || 0);
            fd.append('csrf_token_form', window.nablaCsrf);
            try{
                await fetch(window.nablaUploadUrl,{method:'POST',credentials:'same-origin',body:fd});
            }catch(e){
                console.error('Nabla upload failed',e);
            }
        }
        btn.addEventListener('click', function(){
            if(!recorder){
                start().catch(e=>console.error('Recording failed',e));
            }else if(recorder.state==='recording'){
                stop();
            }
        });
    });
})();
