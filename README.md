[![Build Status](https://travis-ci.org/andela-badebiyi/checkpoint-3.svg?branch=develop)](https://travis-ci.org/andela-badebiyi/checkpoint-3)
<a href="https://styleci.io/repos/49193992"><img src="https://styleci.io/repos/49193992/shield" alt="StyleCI"></a>
[![Coverage Status](https://coveralls.io/repos/andela-badebiyi/checkpoint-3/badge.svg?branch=master&service=github)](https://coveralls.io/github/andela-badebiyi/checkpoint-3?branch=master)

# Checkpoint-3 (Naija Emoji API)
##Summary
This project is a simple restful Naija Emoji api where authenticated users can fetch, create, delete and update emojis. This api makes use of a token based authentication system.

##Usage
**User login**

Send a `post` request to `http://bd-naijaemoji.herokuapp.com/auth/login` with post data `username` and `password`
```
curl -i -X POST -H 'Content-Type: application/json' -d '{"username": "your username", "password": "your password"}' http://bd-naijaemoji.herokuapp.com/auth/login
```

**User Logout**

Send a `get` request to `http://bd-naijaemoji.herokuapp.com/auth/logout` with the user `token` in the header
```
curl -i -X GET -H 'Content-Type: application/json; user-token: user_token' http://bd-naijaemoji.herokuapp.com/auth/login
```

**Create Emoji**

Send a `post` request to `http://bd-naijaemoji.herokuapp.com/emojis` with the emoji data passed via post and the user token passed in the headers
```
curl -i -X POST -H 'Content-Type: application/json; user-token: user_token' -d 
'{
"name": "smiley name", 
"smiley": "smiley character",
"category": "smiley category",
"keywords": "smiley keywords",
}'
http://bd-naijaemoji.herokuapp.com/emojis
```

**Update Emoji**

Send a `put` or `patch` request to `http://bd-naijaemoji.herokuapp.com/emojis/{emoji_id}` with the emoji data passed via post and the user token passed in the headers
```
curl -i -X PUT/PATCH -H 'Content-Type: application/json; user-token: user_token' -d 
'{
"name": "smiley name", 
"smiley": "smiley character",
"category": "smiley category",
"keywords": "smiley keywords",
}'
http://bd-naijaemoji.herokuapp.com/emojis/{emoji_id}
```

**Delete Emoji**

Send a `delete` request to `http://bd-naijaemoji.herokuapp.com/emojis/{emoji_id}` with the user token passed in the headers
```
curl -i -X DELETE -H 'Content-Type: application/json; user-token: user_token' http://bd-naijaemoji.herokuapp.com/emojis/{emoji_id}
```

**Fetch All Emojis**
Send a `get` request to `http://bd-naijaemoji.herokuapp.com/emojis`
```
curl -i -X GET -H 'Content-Type: application/json' http://bd-naijaemoji.herokuapp.com/emojis/
```

**Fetch Emoji by ID**
Send a `get` request to `http://bd-naijaemoji.herokuapp.com/emojis/{emoji_id}`
```
curl -i -X GET -H 'Content-Type: application/json' http://bd-naijaemoji.herokuapp.com/emojis/{emoji_id}
```
