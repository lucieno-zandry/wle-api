# How to deploy

## Run the container

```
docker run --name {image_name} -p 8010:80 -d lucienozandry/{image_name}:latest
```

or 

```
docker run --name app_name_api -p 9000:80 -e APP_URL=http://102.16.254.6:9000 -v /etc/docker/app_name/api/dev/storage:/var/www/html/storage -v /etc/docker/app_name/api/dev/.env:/var/www/html/.env -d lucienozandry/app_name-api:latest
```

## Run typesense container

```
  services:
  typesense:
    image: typesense/typesense:30.1
    restart: on-failure
    ports:
      - "8108:8108"
    volumes:
      - ./typesense-data:/data
    command: '--data-dir /data --api-key=xyz --enable-cors'
```

## Run Redis

```
docker run --name redis -p 6379:6379 -d redis
```