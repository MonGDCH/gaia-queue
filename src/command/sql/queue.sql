CREATE TABLE IF NOT EXISTS `queue_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '链接名称',
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '队列名称',
  `send_time` datetime NOT NULL COMMENT '投递时间',
  `send_data` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '投递数据',
  `run_time` datetime NOT NULL COMMENT '执行任务时间',
  `running_time` float unsigned NOT NULL COMMENT '执行所用时间',
  `status` tinyint(1) unsigned NOT NULL COMMENT '执行返回状态: 0-失败 1-成功',
  `result` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '任务执行结果描述',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建日期',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `item` (`connection`,`queue`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='消息队列执行日志表';