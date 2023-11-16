/**
 * Media Manager
 */
class Media {

    /**
     * Media Manager constructor
     * @param {Ajax} ajax
     */
    constructor(ajax) {
        this.ajax          = ajax;

        this.uploadedFiles = 0;
        this.uploadedSize  = 0;
        this.totalFiles    = 0;
        this.totalSize     = 0;
    }

    /**
     * Initializes the Media Manager
     * @returns {Void}
     */
    init() {
        const $upload   = $(".dropzone-upload");
        this.$drop      = $(".dropzone-drop");
        this.$uploading = $(".dropzone-uploading");
        this.$count     = $(".dropzone-count");
        this.$total     = $(".dropzone-total");
        this.$progress  = $(".dropzone-progress");
        this.$status    = $(".dropzone-status");
        this.$input     = $(".dropzone-input");
        this.$filter    = $(".media-filter");

        if (this.$drop.length) {
            const drop = this.$drop[0];
            window.addEventListener("dragenter", this.startDrop.bind(this));
            drop.addEventListener("dragenter",   this.allowDrag.bind(this));
            drop.addEventListener("dragover",    this.allowDrag.bind(this));
            drop.addEventListener("dragleave",   this.endDrop.bind(this));
            drop.addEventListener("drop",        this.handleDrop.bind(this));
        }
        if ($upload.length && this.$input.length) {
            $upload.on("click", ()  => this.$input.click());
            this.$input.on("change", (e) => this.handleSubmit(e));
        }
        if (this.$filter.length) {
            this.$filter[0].addEventListener("input", this.filterList.bind(this));
        }

        $(".dialog-media .media-sel").removeClass("fancybox");
        $(".media-file").draggable({
            addClasses : false,
            revert     : "invalid",
            cancel     : ".media-actions",
            cursor     : "move",
        });
        $(".media-dir").droppable({
            addClasses : false,
            drop       : async (e, ui) => {
                const $item    = ui.draggable;
                const response = await this.ajax.get("media/move", {
                    newPath : $(e.target).attr("data-route"),
                    oldPath : $item.attr("data-path"),
                    name    : $item.attr("data-name"),
                });
                this.ajax.resolveResponse(response);
            }
        });
    }

    /**
     * Filters the Media List
     * @returns {Void}
     */
    filterList() {
        const search = String(this.$filter.val()).toLocaleLowerCase();
        const $names = $(".media-name");

        $names.each((index, elem) => {
            const $elem = $(elem);
            const name  = $elem.text().toLocaleLowerCase();
            if (name && name !== "..." && name.indexOf(search) === -1) {
                $elem.parent().hide();
            } else {
                $elem.parent().show();
            }
        });
    }



    /**
     * Allows Dragging
     * @param {DragEvent} e
     * @returns {Void}
     */
    allowDrag(e) {
        e.dataTransfer.dropEffect = "copy";
        e.preventDefault();
    }

    /**
     * Starts a Drop
     * @param {DragEvent} e
     * @returns {Void}
     */
    startDrop(e) {
        let hasFiles = false;
        if (e.dataTransfer.types) {
            for (const type of e.dataTransfer.types) {
                if (type === "Files") {
                    hasFiles = true;
                    break;
                }
            }
        }
        if (hasFiles) {
            this.$drop.addClass("dropzone-show");
        }
    }

    /**
     * Ends a Drop
     * @returns {Void}
     */
    endDrop() {
        this.$drop.removeClass("dropzone-show");
    }

    /**
     * Handles the Drop
     * @param {DragEvent} e
     * @returns {Void}
     */
    handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        const files = [];

        // Use DataTransferItemList interface to access the file(s)
        if (e.dataTransfer.items) {
            // @ts-ignore
            for (const item of e.dataTransfer.items) {
                if (item.kind === "file") {
                    const file = item.getAsFile();
                    files.push(file);
                }
            }
        // Use DataTransfer interface to access the file(s)
        } else {
            // @ts-ignore
            for (const file of e.dataTransfer.files) {
                files.push(file);
            }
        }

        // Upload Each file
        this.uploadFiles(files);
        this.endDrop();
    }

    /**
     * Handles the Submit
     * @param {JQuery.ChangeEvent} e
     * @returns {Void}
     */
    handleSubmit(e) {
        e.preventDefault();
        this.uploadFiles(e.target.files);
        this.endDrop();
    }

    /**
     * Uploads the given Files
     * @param {Array.<File>} files
     * @returns {Void}
     */
    uploadFiles(files) {
        const upload = [];
        for (const file of files) {
            const size = file.size / (1024 * 1024);
            if (size <= 7) {
                upload.push(file);
                this.totalSize += file.size;
            }
        }

        if (upload.length !== files.length) {
            $(".result-bar").toggle(false);
            const $result = $(".result-size");
            if (!$result.is(":visible")) {
                $result.toggle(true);
                window.setTimeout(() => $result.fadeOut(), 10000);
            }
        }

        if (!upload.length) {
            return;
        }

        this.totalFiles += upload.length;
        this.$uploading.toggle(true);
        this.$count.text(this.uploadedFiles);
        this.$total.text(this.totalFiles);
        this.updateProgress(0);

        for (const file of upload) {
            this.uploadFile(file);
        }
    }

    /**
     * Uploads a File
     * @param {File} file
     * @returns {Void}
     */
    uploadFile(file) {
        const href     = this.$input.data("href");
        const path     = this.$input.data("path");
        const url      = new URL(href);
        const formData = new FormData();

        formData.append("ajax", "1");
        formData.append("path", path);
        formData.append("file", file);
        if (this.ajax.jwt) {
            formData.append("jwt", this.ajax.jwt);
        }

        const request = new XMLHttpRequest();
        request.upload.addEventListener("progress", (e) => this.updateProgress(e.loaded), false);
        request.addEventListener("load", () => this.uploadCompleted(request), false);

        // @ts-ignore
        request.open("POST", url);
        request.send(formData);
    }

    /**
     * Updates the Progress bar
     * @param {Number} amount
     * @returns {Void}
     */
    updateProgress(amount) {
        this.uploadedSize += amount;
        const percent = Math.min(99, Math.round((this.uploadedSize  * 100) / this.totalSize));
        this.$progress.val(percent);
        this.$status.text(`${percent}%`);
    }

    /**
     * Completes the Upload
     * @param {XMLHttpRequest} request
     * @returns {Void}
     */
    uploadCompleted(request) {
        this.uploadedFiles += 1;
        this.$count.text(this.uploadedFiles);
        if (this.uploadedFiles !== this.totalFiles) {
            return;
        }

        this.uploadedFiles = 0;
        this.uploadedSize  = 0;
        this.totalFiles    = 0;
        this.totalSize     = 0;
        this.$uploading.toggle(false);

        if (request.status < 400 && request.responseText) {
            const response = JSON.parse(request.responseText);
            this.ajax.resolveResponse(response);
        }
    }



    /**
     * Resize the Images one at the time
     * @returns {Promise}
     */
    async resizeImages() {
        const $lis      = $(".resizer-files li");
        const $count    = $(".resizer-count");
        const $progress = $(".resizer-progress");
        const $resized  = $(".resizer-resized");
        const $failed   = $(".resizer-failed");
        const $result   = $(".resizer-result");
        const $success  = $(".resizer-success");
        const $error    = $(".resizer-error");
        const total     = $lis.length;
        let   resized   = 0;
        let   failed    = 0;

        for (let i = 0; i < total; i++) {
            const $elem = $($lis[i]);
            const path  = $elem.attr("data-path");

            try {
                const response = await this.ajax.get("media/resizeOne", { path });
                if (response.success) {
                    resized += 1;
                    $count.text(resized);
                    $resized.text(resized);
                    $progress.attr("value", resized);
                    $elem.find("span").removeClass("icono-blank").addClass("icono-check");
                    $elem[0].scrollIntoView({ behavior : "smooth", block : "center", inline : "center" });
                }
            } catch (e) {
                failed += 1;
                $failed.text(failed);
            }
        }

        $result.show();
        if (resized > 0) {
            $success.show();
            $success[0].scrollIntoView({ behavior : "smooth", block : "center", inline : "center" });
        }
        if (failed > 0) {
            $error.show();
            $error[0].scrollIntoView({ behavior : "smooth", block : "center", inline : "center" });
        }
    }
}
