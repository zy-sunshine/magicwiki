magicwiki
=========

wiki for magiclinux and magicinstaller

## 安装部署需要注意的地方

### 打开php 的 APC 缓存功能
mediawiki 有多种缓存方式，可以加快网页打开速度，所以一定要开启，在实际部署的时候本人采用的 APC 功能，该功能需要 LocalProfiles.php 中开启 APC 功能。
在 dreamhost 上可以不用 root 权限编译开启 APC 功能，请不要忽视。

在mediawiki上有一段快速指南：

http://www.mediawiki.org/wiki/User:Aaron_Schulz/How_to_make_MediaWiki_fast
