const fs = require('fs');
var glob = require("glob");

// fetch command line arguments
// https://www.sitepoint.com/pass-parameters-gulp-tasks/
let arg = (argList => {

  let arg = {}, a, opt, thisOpt, curOpt;
  for (a = 0; a < argList.length; a++) {

    thisOpt = argList[a].trim();
    opt = thisOpt.replace(/^\-+/, '');

    if (opt === thisOpt) {

      // argument value
      if (curOpt) arg[curOpt] = opt;
      curOpt = null;

    }
    else {

      // argument name
      curOpt = opt;
      arg[curOpt] = true;

    }

  }

  return arg;

})(process.argv);

const myFiles = arg.location + '/main.*.bundle.js';

glob(myFiles, {nonull:true}, function (er, files) {
  // files is an array of filenames.
  // If the `nonull` option is set, and nothing
  // was found, then files is ["**/*.js"]
  // er is an error object or null.
  if (er !== null) {
    console.log('log error');
    console.log(er);
  }

  // Fix relative paths (as used in the CSS) to load fonts, images and icons
  // in generated Storybook

  for (let i in files) {
    const f = files[i];

    fs.readFile(f, 'utf8', function (err,data) {

      if (err) {
        return console.log(err);
      }

      // eg. find: url(\"../fonts/
      // replace with: url(\"

      var result = data.replace(/url\(\\"..\/fonts\//g, 'url\(\\"');
      result = result.replace(/url\(\\"..\/images\//g, 'url\(\\"');
      result = result.replace(/url\(\\"..\/icons\//g, 'url\(\\"');

      fs.writeFile(f, result, 'utf8', function (err) {
        if (err) return console.log(err);
      });
    });
  }

});
