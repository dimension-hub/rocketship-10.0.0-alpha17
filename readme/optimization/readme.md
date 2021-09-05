# Optimization

## Images

As of Rocketship Profile version 1.0-alpha9, the install profile provides some basic image optimization, using imageapi_optimize module. However, it is only enabled and set up on the dev and staging environments.  
The reason for this, is because imageapi_optimize needs binaries to be installed on the server to use tools like OptiPNG and JpegOptim and the Dropsolid servers have those installed by default but it might not be present on the externally hosted projects.

So, knowing this: if you want to use imageapi_optimize on Live (or your local environment), you will have to:
- make sure you have at least those 2 binaries installed on your servers
- configure one or more pipelines that use them.

Setting those up is very easy. You can either take a look at the config of dev and staging or do it manually. Here is the workflow for adding a default pipeline to compress jpg and png images: 

### Binaries on server

Make sure the binaries for OptiPNG en JpegOptim are installed on your server.  
Ideally, this was a hard requirement for the server that was set up for your project and communicated well in advance. Check in with the party responsible for hosting if these binaries are installed or can be added.
If you want it on you local environment, you will have to add it to your stack yourself. A quick online search should help with that.

### Enable the modules

You need to enable 2 modules: imageapi_optimize and imageapi_optimize_binaries

### Add pipelines

- Go to /admin/config/media/imageapi-optimize-pipelines
- Choose 'Add optimization pipeline'
- Name it something like 'default recompression' (we will set up 1 pipeline to use as default on all image styles)

### Add processors

For a default pipeline, I would recommend adding compression for PNG and well as JPG. So you need to add 2 processors:
- For the first one, choose OptiPNG
- fill in the path to your binary (usually something like `/usr/bin/optipng`)
- Optimization level is fine at 5
- Don't change the interlace (or set it to non-interlaced)
- For the second one, choose jpegOptim and follow similar steps
- Leave 'Progressive' to 'No change'
- Quality and Target size can be set to 85, but change as needed. Eg. if your images need to have the best of quality, make it higher.

### Set as default

Go back to 'Image Optimize Pipelines' and choose your new pipeline to be the default under 'Sitewide default pipeline'.  
This will now be used for all image styles (you might want to `drush image-flush` them if you already have images uploaded).

That's it!
