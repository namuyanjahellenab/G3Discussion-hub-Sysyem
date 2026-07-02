module com.discussionhub.client {
    requires javafx.controls;
    requires javafx.fxml;
    requires java.sql;
    requires java.desktop;
    requires org.xerial.sqlitejdbc;

    opens com.discussionhub.client to javafx.fxml;
    exports com.discussionhub.client;
    exports com.discussionhub.client.database;
}