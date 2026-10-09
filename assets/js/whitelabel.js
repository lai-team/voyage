var customPreviewMessage = function() {
    return '<b>Post preview is being generated!</b>';
};

wp.hooks.addFilter( 'editor.PostPreview.interstitialMarkup', 'voyage/custom-preview-message', customPreviewMessage );
