# Missenger!

## Docker-first local setup

This project is intended to run without installing Composer or Node dependencies on the host.
Dependencies are installed inside Docker volumes.

```bash
docker compose up --build
docker compose exec app php artisan migrate
```

Open the app at http://localhost:8080. Soketi listens on ws://localhost:6001.

Useful commands:

```bash
docker compose exec app php artisan test
docker compose exec app php artisan route:list
docker compose run --rm assets npm run production
```



 Welcome |          Register            |   Login
:-------------------------:|:-------------------------:|:-------------------------:
![image](https://user-images.githubusercontent.com/56166165/150930212-56aef569-bcc5-46ed-8869-c6b2bbd775ea.png) |  ![image](https://user-images.githubusercontent.com/56166165/150930628-28bfefe6-e335-47b0-acc6-fe30b1fca3a7.png) |  ![image](https://user-images.githubusercontent.com/56166165/150930396-068e339e-575e-4f82-85fc-785e79d2b3a1.png)


Message!             |  Search!
:-------------------------:|:-------------------------:
![image](https://user-images.githubusercontent.com/56166165/150932859-77dc5ae4-da9b-44ff-b7ba-ca4dbc257d17.png) |  ![search](https://user-images.githubusercontent.com/56166165/150941668-35bba52e-67eb-4e48-b497-9cb206770d8c.png)





Create new chat room!             |  Join other rooms!
:-------------------------:|:-------------------------:
![image](https://user-images.githubusercontent.com/56166165/150935565-fff0fa2c-66f9-4eda-af63-a092d115b705.png)  |  ![image](https://user-images.githubusercontent.com/56166165/150935429-7797dee5-a509-4332-a756-5db380723d47.png)








