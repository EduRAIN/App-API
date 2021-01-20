# App-API

Laravel PHP back-end for user-facing webapp

# Prerequisites

- Composer

# Installation

1. Clone the repo
   git clone https://github.com/EduRAIN/App-API.git

2. Install Composer
   composer install

3. Create a .env in the root directory of the project named .env with the following contents.
   DB_CONNECTION=mysql
   DB_HOST=edurain1new.cejgg0bagt8i.us-east-2.rds.amazonaws.com
   DB_PORT=3306
   DB_DATABASE=edurain
   DB_USERNAME=admin
   DB_PASSWORD=Edurain!123

   AWS_ACCESS_KEY_ID = AKIAIFZN24U6HA2MTMUA
   AWS_SECRET_KEY = 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
   AWS_REGION = us-east-2
   AWS_VERSION = 2014-11-01
   AWS_KMS_ARN = 5e678708-29de-47c5-b578-087e6215664e

4. run the project

# Roadmap

- See the Jira board for tasks.

# Contributing

1.  Clone the repo
2.  Create your Feature Branch (git checkout -b issue-#-short-description)
3.  Commit your Changes (git commit -m 'Adding a feature')
4.  Push to the Branch (git push origin issue-#-short-description)
5.  Open a Pull Request
6.  Once merged into master, merge master into production to trigger the pipeline build so update production servers.
