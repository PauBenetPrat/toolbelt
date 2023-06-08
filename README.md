# Toolbelt

Toolbelt is a standalone application built with Laravel Zero that allows you to compare branches, tags, and commits in a Git repository. The application can be compiled and used without any external dependencies, making it convenient to integrate into your project.

## Installation

To use Toolbelt, follow these steps:

1. Clone the repository:

   ```shell
   git clone https://github.com/PauBenetPrat/toolbelt.git

2. Give execute permissions to the compiled file:
    ```shell 
    git clone https://github.com/PauBenetPrat/toolbelt.git

3. Give execute permissions to the compiled file:
    ```shell
    chmod +x builds/toolbelt

4. Copy build toolbelt file to any project you need it
5. Run toolbelt from the project folder. It compares branches "dev" and "revo" by default:
    ```shell
    ./toolbelt git-compare
    ```
    
6. Optional flags. You can pass different branches, tags, or commits as arguments:

    ```shell
    ./toolbelt git-compare dev master
    ```

   - Use the -S or --skip-api-calls flag if you don't have a Bitbucket API token or want to skip linear history checks. 
   - Use the -O flag to open links in your default browser (it's recommended to have dual monitors for this).
   - Use the --no-fetch flag to skip the initial git fetch origin command.
   - Use the --skip and --limit flags to skip and limit the number of commits to check.

7. You can pass your bitbucket api token (retrieved from https://bitbucket.org/<workspace>/<project>/admin/access-tokens) dynamically or set it to each project .env at BITBUCKET_API_TOKEN variable.

# Contribution
Contributions to Toolbelt are welcome! If you find any issues or have suggestions for improvements, please feel free to open an issue or submit a pull request.

# License
Toolbelt is open-source software licensed under the MIT license.
