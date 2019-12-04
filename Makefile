update:
	wget https://github.com/lionsoul2014/ip2region/raw/master/data/ip2region.db && mv -f ip2region.db ./data/ip2region.db
unittest:
	./vendor/bin/phpunit test/*
