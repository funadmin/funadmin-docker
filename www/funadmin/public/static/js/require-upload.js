// +----------------------------------------------------------------------
// | FunAdmin极速开发框架 [基于layui开发]
// +----------------------------------------------------------------------
// | Copyright (c) 2020-2030 http://www.funadmin.com
// +----------------------------------------------------------------------
// | git://github.com/funadmin/funadmin.git 994927909
// +----------------------------------------------------------------------
// | Author: yuege <994927909@qq.com> Apache 2.0 License Code
define(["jquery", 'croppers'], function($, croppers) {
    var upload = layui.upload;
    var croppers = layui.croppers;
    var Upload = {
        init: {
            requests: {
                upload_url: '/ajax/uploads',
                attach_url: '/ajax/getAttach',
                select_url:'/sys.attach/selectfiles'
            },
            upload_exts: Config.upload.upload_file_type,
            upload_size: Config.upload.upload_file_max,
            upload_slice: Config.upload.upload_slice,
            upload_slicesize: Config.upload.upload_slicesize,
        },
        //事件
        events: {
            //附件多图
            mutiUpload: function() {
                Upload.api.mutiUpload()
            },
            //单图或多图文
            uploads: function() {
                Upload.api.uploads();
            },
            //裁剪
            cropper: function() {
                Upload.api.cropper();
            },
        },
        api: {
            mutiUpload: function() {
                //多文件列表示例
                var uploadList = $('*[lay-filter="multipleupload"]');
                layui.each(uploadList, function(i, v) {
                    var uploadListView = $(this).parent('.layui-upload').find('.uploadList');
                    var id = $(this).attr('id');
                    var uploadListBtn = $(this).parent('.layui-upload').find('.uploadListBtn').attr('id');
                    var upload = layui.upload ? layui.upload : parent.layui.upload;
                    uploadListIns = upload.render({
                        elem: '#'+uploadListBtn,
                        url: Fun.url(Upload.init.requests.upload_url) //改成您自己的上传接口
                        , accept: 'file',
                        drag: true,
                        multiple: true,
                        auto: false,
                        bindAction: '#'+id,
                        choose: function(obj) {
                            var files = this.files = obj.pushFile(); //将每次选择的文件追加到文件队列
                            //读取本地文件
                            obj.preview(function(index, file, result) {
                                var tr = $(['<tr id="upload-' + index + '">', '<td>' + file.name + '</td>', '<td>' + (file.size / 1024).toFixed(1) + 'kb</td>', '<td class="progress"> 0 </td>', '<td>等待上传</td>', '<td>', '<button class="layui-btn layui-btn-xs fun-upload-reload layui-hide">重传</button>', '<button class="layui-btn layui-btn-xs layui-btn-danger fun-upload-delete">删除</button>', '</td>', '</tr>'].join(''));
                                //单个重传
                                tr.find('.fun-upload-reload').on('click', function() {
                                    obj.upload(index, file);
                                });
                                //删除
                                tr.find('.fun-upload-delete').on('click', function() {
                                    delete files[index]; //删除对应的文件
                                    tr.remove();
                                    uploadListIns.config.elem.next()[0].value = ''; //清空 input file 值，以免删除后出现同名文件不可选
                                });
                                uploadListView.append(tr);
                            });
                        },
                        progress: function(n, elem) {
                            var percent = n + '%'; //获取进度百分比
                            $('.progress').html(percent); //可配合 layui 进度条元素使用
                        },
                        done: function(res, index, upload) {
                            if (res.code > 0) { //上传成功
                                var tr = uploadListView.find('tr#upload-' + index),
                                    tds = tr.children();
                                tds.eq(3).html('<span style="color: #5FB878;">上传成功</span>');
                                tds.eq(4).html(''); //清空操作
                                var table = parent.$('body').find('table.layui-table[lay-filter');
                                if(table.length > 0) {
                                    var id = table.attr('id');
                                    id && parent.layui.table && parent.layui.table.reload(id);
                                }
                                return delete this.files[index]; //删除文件队列已经上传成功的文件
                            }
                            this.error(index, upload, res);
                        },
                        error: function(index, upload, res) {
                            var tr = uploadListView.find('tr#upload-' + index),
                                tds = tr.children();
                            tds.eq(3).html('<span style="color: #FF5722;">上传失败(' + __(res.msg) + ')</span>');
                            tds.eq(4).find('.demo-reload').removeClass('layui-hide'); //显示重传
                        }
                    });

                })
            },
            uploads: function() {
                var uploadList = $('*[lay-filter="upload"]');
                if (uploadList.length > 0) {
                    layui.each(uploadList, function(i, v) {
                        //普通图片上传
                        var data = $(this).data();
                        if(typeof data.value == 'object') data = data.value;
                        var uploadNum = data.num,
                            uploadMime = data.mime;
                        var uploadAccept = data.accept,
                            uploadPath = data.path || 'upload',
                            uploadSize = data.size,
                            uploadmultiple = data.multiple,
                            uploadExts = data.exts;
                        uploadNum = uploadNum || 1;
                        uploadSize = uploadSize || Upload.init.upload_size;
                        uploadExts = uploadExts || Upload.init.upload_exts;
                        uploadExts = uploadExts.indexOf(',') ?uploadExts.replace(/,/g,'|'):uploadExts
                        uploadmultiple = uploadmultiple || false;
                        uploadAccept = uploadAccept || uploadMime;
                        uploadAccept = uploadAccept ==='*' ?'file':uploadAccept;
                        var _parent = $(this).parents('.layui-upload')
                        var input = _parent.find('input[type="text"]');
                        var options = {}
                        options = {
                            elem: this,
                            accept: uploadAccept,
                            size: uploadSize,
                            number:uploadNum,
                            multiple: uploadmultiple,
                            url: Fun.url(Upload.init.requests.upload_url) + '?path=' + uploadPath,
                            before: function(obj) {
                                var index = Fun.toastr.loading(__('uploading...'))
                            },
                            done: function(res) {
                                if (res.code > 0) {
                                    var img ='jpg|jpeg|png|gif|svg|bmp|webp';
                                    var video ='mp4|rmvb|avi|ts';
                                    var zip ='jpg|jpeg|png|gif|';
                                    var audio ='mp3|wma|wav';
                                    var office ='ppt|pptx|xls|xlsx|word|ppt|pptx|doc|docx';
                                    var start = res.url.lastIndexOf(".");
                                    uploadAccept =  res.url.substring(start+1, res.url.length).toLowerCase();
                                    if (img.indexOf(uploadAccept) !==-1) {
                                        html = '<li><img lay-event="photos" class="layui-upload-img fl" width="150" src="' + res.url + '"><i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="' + res.url + '"></i></li>\n';
                                    } else if (zip.indexOf(uploadAccept) !==-1) {
                                        html = '<li><img  class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/zip.jpg"><i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="' + res.url + '"></i></li>\n';
                                    } else if (video.indexOf(uploadAccept) !==-1) {
                                        html = '<li><img  class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/video.jpg"><i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="' + res.url + '"></i></li>\n';
                                    } else if (audio.indexOf(uploadAccept) !==-1) {
                                        html = '<li><img  class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/audio.jpg"><i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="' + res.url + '"></i></li>\n';
                                    } else if (office.indexOf(uploadAccept) !==-1) {
                                        html = '<li><img  class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/office.jpg"><i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="' + res.url + '"></i></li>\n';
                                    } else {
                                        html = '<li><img  class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/file.jpg"><i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="' + res.url + '"></i></li>\n';
                                    }
                                    var inputVal = input.val();
                                    if (uploadNum == 1) {
                                        input.val(res.url);
                                        _parent.find('.layui-upload-list').html(html)
                                    } else if (uploadNum == '*') {
                                        _parent.find('.layui-upload-list').append(html)
                                        if (inputVal) {
                                            val_temp = (inputVal + ',' + res.url)
                                        } else {
                                            val_temp = res.url
                                        }
                                        input.val(val_temp);
                                    } else {
                                        if (_parent.find('li').length >= uploadNum) {
                                            Fun.toastr.error(__('File nums is limited'), function() {
                                                setTimeout(function() {
                                                    Fun.toastr.close();
                                                }, 2000)
                                            })
                                            return false;
                                        } else {
                                            _parent.find('.layui-upload-list').append(html)
                                            if (inputVal) {
                                                val_temp = (inputVal + ',' + res.url)
                                            } else {
                                                val_temp = res.url
                                            }
                                            input.val(val_temp);
                                        }
                                    }
                                    Fun.toastr.success(__('Upload Success'), function() {
                                        setTimeout(function() {
                                            Fun.toastr.close();
                                        }, 2000)
                                    })
                                } else {
                                    Fun.toastr.error(__('Upload Failed') + __(res.msg), function() {
                                        setTimeout(function() {
                                            Fun.toastr.close();
                                        }, 2000)
                                    })
                                }
                                Fun.toastr.close(index);
                            },
                            error: function() {
                                Fun.toastr.error(__('Upload Failed'), function() {
                                    setTimeout(function() {
                                        Fun.toastr.close();
                                    }, 2000)
                                })
                                Fun.toastr.close();
                            }
                        }
                        if(uploadExts!=="*" && uploadExts){
                            options.exts = uploadExts
                        }
                        var uploadInt = layui.upload.render(options);
                        Toastr.destroyAll();

                    })
                }
            },
            cropper: function() {
                var cropperlist = $("*[lay-filter='cropper']");
                if (cropperlist.length > 0) {
                    cropperlistobj = {}
                    layui.each(cropperlist, function(i) {
                        //创建一个头像上传组件
                        var _parent = $(this).parents('.layui-upload'), id = $(this).prop('id');
                        var data = $(this).data();
                        if(typeof data.value == 'object') data = data.value;
                        var saveW = data.width, saveH = data.height, mark = data.mark,
                            area = data.area, uploadPath = data.path || 'upload';
                        saveW = saveW || 300;
                        saveH = saveH || 300;
                        mark = mark || 1;
                        area = area || '720px';
                        cropperlistobj[i] = layui.croppers.render({
                            elem: '#' + id,
                            saveW: saveW, //保存宽度
                            saveH: saveH, //保存高度
                            mark: mark ,//选取比例
                            area: area, //弹窗宽度
                            url: Fun.url(Upload.init.requests.upload_url) + '?path=' + uploadPath //图片上传接口返回和（layui 的upload 模块）返回的JOSN一样
                            ,
                            done: function(res) {
                                //上传完毕回调
                                if (res.code > 0) {
                                    Fun.toastr.success(res.msg);
                                    _parent.find('input[type="text"]').val(res.url)
                                    var html = '<li><img lay-event="photos" class="layui-upload-img fl" width="150" src="' + res.url + '"><i class="layui-icon layui-icon-close" lay-event="upfileDelete" lay-fileurl="' + res.url + '"></i></li>\n';
                                    _parent.find('.layui-upload-list').html(html)
                                } else if (res.code <= 0) {
                                    Fun.toastr.error(res.msg);
                                }
                            }
                        });
                    })
                }
            },
            bindEvent: function() {
                Upload.events.mutiUpload();
                Upload.events.uploads();
                Upload.events.cropper();
            }
        }
    }
    return Upload;
})