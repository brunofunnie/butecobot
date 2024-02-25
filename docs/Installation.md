# Chorume Bot

⬅ [Back to README.md](../README.md)

This bot was developed using [DiscordPHP 7.3](https://github.com/discord-php/DiscordPHP)

##  Requirements

- PHP 8.2
- Composer
- Docker

##  Third Party APIs

- OpenAI (just the API token)
- ElevenLabs (just the API token)

##  Installation

The initial setup é pretty easy:

    git clone https://github.com/brunofunnie/chorumebot

Navigate to the created directory and then run:

	cp .env-example .env

Well I think that as first step to get into the Discord World would be to create a server for you to test the Bot, I recommend create a server especifically for developing and testing the Bot. Create a server is pretty easy, just click in the **Plus** button in your Discord app and follow the instructions.

![](https://github.com/brunofunnie/chorumebot/blob/main/docs/images/0.png?raw=true)

Now you'll need to create a Bot in the "Developer Portal", so navigate to [Developer Portal](https://discord.com/developers/applications) -> Applications and click in the **New Application** button in the top right of the screen.

![](https://github.com/brunofunnie/chorumebot/blob/main/docs/images/1.png?raw=true)

Type a name for the Bot, mark the checkbox and click on the "Create" button.

![](https://github.com/brunofunnie/chorumebot/blob/main/docs/images/2.png?raw=true)

After that you Bot Application will be created, now expand **OAuth2** menu, click on **URL Generator** and in the page that will be opened mark the following checkboxes:

Scopes
- bot
- applications.commands

Bot Permissions
- Administrator (this is for developing only, when creating an account for production choose the proper permissions)

![](https://github.com/brunofunnie/chorumebot/blob/main/docs/images/3.png?raw=true)

Now scroll down to the end of the page and you'll see an url, that url will be the one you'll be using to join your Bot with the server you have. You just need to copy and paste the url in any browser and navigate to it.

![](https://github.com/brunofunnie/chorumebot/blob/main/docs/images/4.png?raw=true)

![](https://github.com/brunofunnie/chorumebot/blob/main/docs/images/5.png?raw=true)

Now let's get the Bot Token to be used by the Bot application. Navigate to [Developer Portal](https://discord.com/developers/applications)  -> Bot, and click in the **Reset Token** button, a new token wil be show in the screen, copy it and add it to your **.env** file in the TOKEN variable.

![](https://github.com/brunofunnie/chorumebot/blob/main/docs/images/6.png?raw=true)

For our final step on this screen, we must enable some **Gateway Intents", since this is a develop environment don't worry about enabling all the three as shown in the image below:

![](https://github.com/brunofunnie/chorumebot/blob/main/docs/images/8.png?raw=true)

You're almost there, now let's run the docker containers, navigate to the **docker** directory and type:

	docker compose up -d

You can check if all the containers are running by using:

	docker ps

You should see something similar to:

![](https://github.com/brunofunnie/chorumebot/blob/main/docs/images/7.png?raw=true)

Before you can install the PHP packages and run the migrations let's create our database that will receive the migrations. Navigate to the PHPMyAdmin, log in it and create a database called:

	chorumebot

I would choose **utf8mb4_general_ci** for the collation. But feel free to choose the one you like, just remember it will influence in the sorting of things for example.

For the next commands you'll need to be in the top level of this repository. The .env copied from the example already contain the default credentials for the dev environment so you'll only need to run the migrations. For that you'll need to:

	composer install && vendor/bin/phinx migrate -e development

If everything went up ok you'll can just run the next command and your Bot will be running:

	php src/index.php

##  Commands

Run migrations:

	vendor/bin/phinx migrate -e development

## Useful urls

- [PHPMyAdmin - A web MySQL Client](http://127.0.0.1:8081)
- [AnotherRedisDesktopManager - Self explanatory name](https://github.com/qishibo/AnotherRedisDesktopManager)
