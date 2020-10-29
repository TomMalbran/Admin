tinymce.PluginManager.add("mediamanager", function (editor) {
    function openDialog(type, selected, callback) {
        let sel = "";
        if (selected && editor.settings.mediamanager_files) {
            const url = selected.replace(editor.settings.mediamanager_files, "");
            sel = `&selected=${url}`;
        }

        const title = editor.settings.mediamanager_title || "Media Manager";
        const path  = editor.settings.mediamanager_folder ? `&path=${editor.settings.mediamanager_folder}` : "";
        const href  = `${editor.settings.mediamanager_path}media?iframe=1&select=1&type=${type}${path}${sel}`;

        // window.ajax.loadDialog(href, "media");
        tinymce.activeEditor.windowManager.openUrl({
            title  : title,
            url    : href,
            width  : 900,
            height : 600,
        });
        tinymce.activeEditor.windowManager.setUrl = (path, name) => {
            if (callback) {
                // A Link
                if (type === "file") {
                    callback(path, { text : name });
                // An Image
                } else if (type == "image") {
                    callback(path, { alt : name });
                // A Video
                } else if (type == "video") {
                    // callback(path, { source2 : "alt.ogg", poster : "image.jpg" });
                    callback(path);
                // Another Type
                } else {
                    callback(path);
                }
            } else {
                const cnt = `<img src="${path}" alt="${name}" />`;
                editor.insertContent(cnt);
            }
        };
    }

    function onClick() {
        editor.focus(true);
        openDialog("image");
    }

    editor.addShortcut("Ctrl+E", "", onClick);
    editor.ui.registry.addButton("mediamanager", {
        icon     : "browse",
        tooltip  : "Insert file",
        shortcut : "Ctrl+E",
        onAction : onClick,
    });
    editor.ui.registry.addMenuItem("mediamanager", {
        icon     : "browse",
        text     : "Insert file",
        shortcut : "Ctrl+E",
        onAction : onClick,
        context  : "insert"
    });


    tinymce.activeEditor.settings.file_picker_callback = (callback, value, meta) => {
        let type = meta.filetype;
        if (type === "media") {
            type = "video";
        }
        openDialog(type, value, callback);
    }
});
