# LaraChat Commands

## /docs

### Usage
#### When you know where the details are
Docs is a simple enough command to use.  It has 3 arguments and 1 option available right now.

A fully formed /docs command would look like the following:
> /docs [version] [section] [sub section]
> /docs master eloquent introduction

This will produce a link to http://laravel.com/docs/master/eloquent#introduction

The command does not require all 3 arguments however.  It appends to the url for each you supply.  So just `/docs` would post to the channel http://laravel.com/docs.

#### WHen you don't know what is available
We understand that not everyone has all sections of all pages memorized.  That is why the -h option is there.

The -h option will send you text (that only you can see) in slack to let you know whats available.

> /docs -h

Will return...

```
Available document versions are:
master    4.2    4.1    4.0
```

The -h option will work at any step you are stuck on.  Add what you know to the command, throw in -h and it will help with the next step.

### Validation
The command handles some basic validation.  If you pass a version, section or sub section that is not recognized, it will alert you with what you sent, and then show you what the available options are (as if you added -h to the command).