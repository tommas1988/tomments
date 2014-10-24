function loadComments() {
    var url          = 'get_comments.php';
    var searchKeyObj = $('#searchKey');
    var originKeyObj = $('#originKey');
    var params       = {};

    if (0 == searchKeyObj.val()) {
        alert('No more comments!');
        return;
    }

    params.length        = $('#length').val();
    params.search_key    = searchKeyObj.val();
    params.search_params = {};

    params.search_params.targetId = $('#targetId').val();
    if (-1 != originKeyObj.val()) {
        params.search_params.originKey = originKeyObj.val();
    }

    $.getJSON(url, params, function(resp) {
        if (undefined !== resp.error) {
            alert(resp.error);
            return;
        }

        if (!resp.nextSearchKey) {
            searchKeyObj.val(0);
        } else {
            searchKeyObj.val(resp.nextSearchKey.searchKey);
            originKeyObj.val(resp.nextSearchKey.originKey);
        }

        for (var i = 0; i < resp.comments.length; i++) {
            showComment(resp.comments[i]);
        }
    });
}

function showComment(data) {
    var clone     = $('#prototype .comment').clone();
    var container = $('#container');
    var originKey = (undefined !== data.originKey) ? data.originKey : -1;

    clone.css('padding-left', data.level * 20);
    clone.find("[name='key']").val(data.key);
    clone.find("[name='level']").val(data.level);
    clone.find("[name='originKey']").val(originKey);
    clone.find('.time').html(data.time);
    clone.find('p').html(data.content);
    clone.find('.reply').click(showReplyBox);
    clone.find('.edit').click(showEditBox);
    clone.find('.delete').click(deleteComment);

    container.append(clone);

    if (undefined !== data.children) {
        for (var i = 0; i < data.children.length; i++) {
            showComment(data.children[i]);
        }
    }
}

function doAddComment(params, callback) {
    var url = 'add_comment.php';

    params.target_id = $('#targetId').val();
    $.post(url, params, function(resp) {
        resp = $.parseJSON(resp);
        if (undefined !== resp.error) {
            alert(resp.error);
            return;
        }

        $('#content').val('');

        if (undefined !== callback) {
            callback();
        }
    });
}

function addComment() {
    var params = {};

    params.content = $('.add-comment-box textarea').val();
    doAddComment(params, function() {
        document.location.reload();
    });
}

function replyComment() {
    var params = {};
    var commentObj = $(this).parent().parent().parent();
    var level;

    level = parseInt(commentObj.find("[name='level']").val(), 10);
    if (0 === level) {
        params.origin_key = commentObj.find("[name='key']").val();
    } else {
        params.origin_key = commentObj.find("[name=originKey]").val();
    }

    params.level      = 1 + level;
    params.parent_key = commentObj.find("[name='key']").val();
    params.content    = commentObj.find('textarea').val();

    doAddComment(params, function() {
        commentObj.find('.cancel').click();
    });
}

function showReplyBox() {
    var commentObj = $(this).parent().parent();

    commentObj.find('.submit').html('Reply Comment');
    commentObj.find('.submit').click(replyComment);

    showCommentBox(commentObj);
}

function showEditBox() {
    var commentObj = $(this).parent().parent();

    commentObj.find('.submit').html('Update Comment');
    commentObj.find('.submit').click(editComment);
    commentObj.find('textarea').val(commentObj.find('p').html());

    showCommentBox(commentObj);
}

function showCommentBox(commentObj) {
    commentObj.find('.cancel').click(cancelComment);
    commentObj.find('.add-comment').show();
    commentObj.find('.operation').hide();
}

function cancelComment() {
    var commentObj = $(this).parent().parent().parent();

    $(this).parent().find('.submit').unbind('click');
    $(this).parent().find('.cancel').unbind('click');

    commentObj.find('.add-comment').hide();
    commentObj.find('.operation').show();
}

function editComment() {
    var url        = 'update_comment.php';
    var params     = {};
    var commentObj = $(this).parent().parent().parent();
    var level      = parseInt(commentObj.find("[name=level]").val(), 10);
    var content    = commentObj.find('textarea').val();

    if (commentObj.find('p').html() === content) {
        return;
    }

    if (0 === level) {
        params.is_child = 1;
    }

    params.content   = content;
    params.key       = commentObj.find("[name='key']").val();
    $.post(url, params, function(resp) {
        resp = $.parseJSON(resp);        
        if (undefined !== resp.error) {
            alert(resp.error);
            return;
        }

        alert('Processed!');
        document.location.reload();
    });
}

function deleteComment() {
    var url        = 'delete_comment.php';
    var params     = {};
    var commentObj = $(this).parent().parent();
    var level      = parseInt(commentObj.find("[name=level]").val(), 10);

    if (0 !== level) {
        params.is_child   = 1;
        params.origin_key = commentObj.find("[name='originKey']").val();
        params.level      = level;
    }

    params.key = commentObj.find("[name='key']").val();
    $.post(url, params, function(resp) {
        resp = $.parseJSON(resp);
        if (undefined !== resp.error) {
            alert(resp.error);
            return;
        }

        alert('Processed!');
        document.location.reload();
    });
}
