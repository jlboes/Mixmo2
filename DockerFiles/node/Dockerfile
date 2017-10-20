FROM node:latest

EXPOSE 9090
RUN mkdir -p /data
WORKDIR /data

COPY package.json /data/
RUN npm install


CMD [ "npm", "start" ]