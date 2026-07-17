module com.discussionhub.client {
    requires javafx.controls;
    requires javafx.fxml;
    requires transitive javafx.graphics;
    requires transitive java.sql;
    requires org.json;
    requires java.desktop;
    requires org.xerial.sqlitejdbc;
    requires pusher.java.client;

    opens com.discussionhub.client to javafx.fxml;

    exports com.discussionhub.client;
    exports com.discussionhub.client.database;
    exports com.discussionhub.client.model;
    exports com.discussionhub.client.quiz;
    exports com.discussionhub.client.utils;
}