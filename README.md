Make sure to read the Getting Started guide below, and be off and running on your project
in 30 seconds flat.

This base WordPress plugin is offered by getwpapps.com - a place for WordPress developers
to promote, support, and get paid for their work. 

Our plugin architecture eases one aspect of promoting your plugin - committing it to the
WordPress.org plugin registry - by showing you how to split your plugin into *lite* and 
*pro* versions, and by scripting deployment into WordPress' Subversion repository.

## Getting Started

**Step 1** Prepare your computer.

You'll need to setup [Git and your GitHub account](http://help.github.com/). 

**Step 2** Create accounts to host your plugin. 

You'll want to consider registering your plugin's namespace on three different sites:

1. [wordpress.org](http://wordpress.org/extend/plugins/add/) - where you'll promote the *lite* version of your plugin
2. [github.com](https://github.com/repositories/new) - where you'll be hosting your source code (the *best* place to promote
   open source, especially when [required to do so](http://wordpress.org/extend/plugins/about/) -
   refer to the part about being required to use a GPLv2-compatible license)
3. [getwpapps.com](http://getwpapps.com/developers) - Coming soon: where we hope you'll be selling licenses to your plugin,
   and supporting the community that forms around your awesome software!

**Step 2** Clone this project.

    ~ #> git clone git@github.com:collegeman/wpapp.git your-plugin
    ~ #> cd your-plugin
    
Or, if you have a master project that groups your plugins and themes together, setup a new submodule

    ~ #> git submodule add git@github.com:collegeman/wpapp.git your-plugin
    ~ #> cd your-plugin
    
**Step 3** Rename the `plugin-name` folder to a name of your choosing. To ensure that it's easy for you
to pull updates from this project, you'll want to make this change using the `git mv` command:

    your-plugin #> git mv plugin-name your-plugin``
    
**Step 4** Update the make file, `make.php`

Here you're going to configure your plugin's meta data - what you normally would fill into the plugin
file, we do for you from this configuration script. The file's documentation explains each setting.
    
**Step 5** Rename our remote, and add your project's GitHub remote.
  
    your-plugin #> git remote rename origin wpapp
    your-plugin #> git remote add origin git@github.com:you/your-plugin.git
    your-plugin #> git push origin master
    
Then, reconfigure your master branch so that your GitHub project is the default target of
push/pull operations (ease-of-use, FTW):
    
    your-plugin #> git branch --set-upstream master origin/master


