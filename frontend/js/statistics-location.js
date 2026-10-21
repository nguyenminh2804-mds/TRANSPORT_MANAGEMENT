const statisticsDestination=new URL('dashboard.html',location.href);statisticsDestination.search=location.search;statisticsDestination.hash='statistics';location.replace(statisticsDestination.href);
